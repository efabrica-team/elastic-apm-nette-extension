# Elastic APM Extension

## Usage

**config.neon**

```yaml
extensions:
     apm: Efabrica\NetteElasticAmp\ApmExtension
     
apm:
    enabled: true # optional
    config: # required (APM agent configuration)
        appName       : "Name of this application" # required
        appVersion    : "Application version" # optional, default: ''
        serverUrl     : "APM Server Endpoint" # optional, default: http://127.0.0.1:8200
        secretToken   : "Secret token for APM Server" # optional, default: null
        hostname      : "Hostname to transmit to the APM Server" # optinal, default: gethostname()
        active        : "Activate the APM Agent" # optional, default: true
        timeout       : "Guzzle Client timeout" # optional, default: 5
        apmVersion    : "APM Server Intake API version" # optional, default: 'v1'
        env           : "$_SERVER vars to send to the APM Server, empty set sends all. Keys are case sensitive" # optional, default: []
        cookies       : "Cookies to send to the APM Server, empty set sends all. Keys are case sensitive" # optional, default: []
        httpClient    : "Extended GuzzleHttp\\Client" # optional, default: []
        backtraceLimit: "Depth of a transaction backtrace" # optional, default: unlimited
    spanGenerators: # optional, default: NetteDatabaseQuerySpanGenerator::class
        - Efabrica\NetteElasticAmp\SpanGenerator\NetteDatabaseQuerySpanGenerator()
```

## How to create new SpanGenerators

Each span generator have to register processSpan() method on APM agent for events we want to capture.

```php
public static function register(ContainerBuilder $containerBuilder, ServiceDefinition $apmTransaction): void
{
    // Some code ...
    $service->addSetup('
        $service->onEventStart[] = function ($param1, $param2) :void {
            (?)->processSpan(?, ?, $param1, $param2);
        }', [$apmTransaction, self::class, 'start']);
    $service->addSetup('
        $service->onEventEnd[] = function ($param1, $param2) :void {
            (?)->processSpan(?, ?, $param1, $param2);
        }', [$apmTransaction, self::class, 'end']);
}

public function process(float $transactionStart, string $method, array $params): array
{
    switch($method) {
        case 'start':
            // do something ...
            return $spans; // array with spans
        case 'end':
            // do something else ...
            return $spans; // array with spans
    }
}
```