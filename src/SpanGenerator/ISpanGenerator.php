<?php

declare(strict_types=1);

namespace Efabrica\NetteElasticAmp\SpanGenerator;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;

interface ISpanGenerator
{
    public static function register(ContainerBuilder $containerBuilder, ServiceDefinition $apmTransaction): void;

    public function process(float $transactionStart, string $method, array $params): array;
}
