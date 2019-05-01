<?php

declare(strict_types=1);

namespace Efabrica\NetteElasticAmp;

use Efabrica\NetteElasticAmp\SpanGenerator\ISpanGenerator;
use Nette\Utils\Strings;
use PhilKra\Agent;
use PhilKra\Events\Transaction;
use PhilKra\Exception\Transaction\DuplicateTransactionNameException;
use PhilKra\Exception\Transaction\UnknownTransactionException;
use Nette;

class ApmTransaction
{
    /** @var float */
    private $start = 0.0;

    /** @var Agent */
    private $agent;

    /** @var Nette\Http\Request */
    private $httpRequest;

    /** @var Nette\Http\Response */
    private $httpResponse;

    /** @var array */
    private $spanGenerators = [];

    /** @var array */
    private $spans = [];

    /** @var string */
    private $transactionName = "unknown";

    /** @var array|null */
    private $currentAppSpan;

    /** @var array|null */
    private $currentRequestSpan;

    public function __construct(array $config, Nette\Http\Request $httpRequest, Nette\Http\Response $httpResponse)
    {
        $this->agent = new Agent($config);
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
    }

    public function start(): void
    {
        $this->start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->transactionName = sprintf("%s %s", Strings::upper($this->httpRequest->getMethod()), $this->httpRequest->getUrl()->getHostUrl() . $this->httpRequest->getUrl()->getPath());

        try {
            $this->agent->startTransaction($this->transactionName, [], $this->start);
        } catch (DuplicateTransactionNameException $e) {
            // @silently ignore: maybe use psr/log?
        }

        $this->currentAppSpan = [
            'startTimestamp' => microtime(true),
            'span' => [
                'name' => 'Application::run',
                'type' => 'app',
                'stacktrace' => [],
                'start' => (microtime(true) - $this->start) * 1000,
            ]
        ];
    }

    public function stop(): void
    {
        $this->spans[] = array_merge($this->currentAppSpan['span'], [
            'duration' => (microtime(true) - $this->currentAppSpan['startTimestamp']) * 1000
        ]);
        $this->currentAppSpan = null;

        try {
            $this->agent->stopTransaction($this->transactionName);

            /** @var Transaction $transaction */
            $transaction = $this->agent->getTransaction($this->transactionName);
            $transaction->setSpans($this->spans);
        } catch (UnknownTransactionException $e) {
            // @silently ignore: maybe use psr/log?
            return;
        }

        $this->agent->send();
    }

    public function request(Nette\Application\Request $request): void
    {
        $this->currentRequestSpan = [
            'startTimestamp' => microtime(true),
            'span' => [
                'name' => sprintf("Request [%s] %s:", $request->getMethod(),$request->getPresenterName()),
                'type' => 'app',
                'stacktrace' => [],
                'start' => (microtime(true) - $this->start) * 1000,
                'tags' => $request->getParameters()
            ]
        ];
    }

    public function response(): void
    {
        $this->spans[] = array_merge($this->currentRequestSpan['span'], [
            'duration' => (microtime(true) - $this->currentRequestSpan['startTimestamp']) * 1000
        ]);
        $this->currentRequestSpan = null;
    }

    public function registerSpanGenerator(ISpanGenerator $spanGenerator): self
    {
        $this->spanGenerators[get_class($spanGenerator)] = $spanGenerator;
        return $this;
    }

    public function processSpan(string $class, string $method, ...$params): void
    {
        if (!isset($this->spanGenerators[$class])) {
            return;
        }

        $spans = $this->spanGenerators[$class]->process($this->start, $method, $params);
        if (!empty($spans)) {
            $this->spans = array_merge($this->spans, $spans);
        }
    }

}
