<?php

declare(strict_types=1);

namespace Efabrica\NetteElasticAmp\SpanGenerator;

use Efabrica\NetteElasticAmp\Helper\Stacktrace;
use Nette\DI\ContainerBuilder;
use Nette\DI\ServiceDefinition;
use Nette\Utils\Strings;
use Nette\Database\Connection;

class NetteDatabaseQuerySpanGenerator implements ISpanGenerator
{
    public static function register(ContainerBuilder $containerBuilder, ServiceDefinition $apmTransaction): void
    {
        $dbConnectionFactoryName = $containerBuilder->getByType(Connection::class);
        if (!$dbConnectionFactoryName) {
            return;
        }

        /** @var ServiceDefinition $dbConnectionFactory */
        $dbConnectionFactory = $containerBuilder->getDefinition($dbConnectionFactoryName);
        $dbConnectionFactory->addSetup('
            $service->onQuery[] = function ($connection, $result) :void {
                (?)->processSpan(?, ?, $connection, $result);
            }', [$apmTransaction, self::class, 'query']);
    }

    public function process(float $transactionStart, string $method, array $params): array
    {
        if ($method !== 'query') {
            return [];
        }

        /** @var Connection $connection */
        $connection = $params[0];
        $result = $params[1];

        return [[
            'name' => Strings::truncate($result->getQueryString(), 50),
            'type' => 'db.mysql.query',
            'stacktrace' => Stacktrace::generate(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), [self::class]),
            'start' => (microtime(true) - $transactionStart) * 1000,
            'duration' => $result->getTime() * 1000,
            'context' => [
                'db' => [
                    'type' => 'sql',
                    'statement' => $result->getQueryString()
                ],
                'tags' => [
                    'queryTime' => $result->getTime(),
                    'parameters' => json_encode($result->getParameters())
                ],
            ],
        ]];
    }
}
