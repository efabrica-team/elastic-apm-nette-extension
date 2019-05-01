<?php

declare(strict_types=1);

namespace Efabrica\NetteElasticAmp;

use Efabrica\NetteElasticAmp\SpanGenerator\NetteDatabaseQuerySpanGenerator;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\DI\Definitions\Statement;
use Nette\DI\ServiceDefinition;

class ApmExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'enabled' => Expect::bool()->default(true),
            'config' => Expect::array(),
            'spanGenerators' => Expect::arrayOf(Statement::class)->default([
                new Statement(NetteDatabaseQuerySpanGenerator::class)
            ])
        ]);
    }

    public function loadConfiguration(): void
    {
        /** @var Structure $config */
        $config = $this->config;

        if (!$config->enabled) {
            return;
        }

        $builder = $this->getContainerBuilder();
        $apmTransaction = $builder->addDefinition($this->prefix('apm'))
            ->setType(ApmTransaction::class)
            ->setFactory(ApmTransaction::class, [$config->config]);

        $applicationFactoryName = $builder->getByType(Application::class);
        if ($applicationFactoryName !== null) {
            /** @var ServiceDefinition $applicationFactory */
            $applicationFactory = $builder->getDefinition($applicationFactoryName);
            $applicationFactory->addSetup('
                $service->onStartup[] = function ($app) :void {
                    (?)->start();
                };', [$apmTransaction]);
            $applicationFactory->addSetup('
                $service->onShutdown[] = function ($app) :void {
                    (?)->stop();
                };', [$apmTransaction]);
            $applicationFactory->addSetup('
                $service->onRequest[] = function ($app, $request) :void {
                    (?)->request($request);
                };', [$apmTransaction]);
            $applicationFactory->addSetup('
                $service->onResponse[] = function ($app, $response) :void {
                    (?)->response($response);
                };', [$apmTransaction]);
        }

        /** @var Statement $spanGenerator */
        foreach ($config->spanGenerators as $spanGenerator) {
            $callback = [$spanGenerator->getEntity(), 'register'];
            if (!is_callable($callback)) {
                continue;
            }

            call_user_func($callback, $builder, $apmTransaction);
            $apmTransaction->addSetup('
                $service->registerSpanGenerator(?)
            ', [$spanGenerator]);
        }
    }
}
