<?php

namespace SamuelBednarcik\ElasticAPMAgent\Collectors\Guzzle;

use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SamuelBednarcik\ElasticAPMAgent\CollectorInterface;
use SamuelBednarcik\ElasticAPMAgent\Events\Span;

class GuzzleCollector implements CollectorInterface
{
    const SPAN_TYPE = 'ext';

    /**
     * @var array
     */
    private $calls = [];

    /**
     * @return Span[]
     */
    public function getSpans(): array
    {
        return $this->createSpans();
    }

    /**
     * Create span instances from calls
     * @return array
     */
    private function createSpans(): array
    {
        $spans = [];

        foreach ($this->calls as $call) {
            $span = new Span();
            $span->setName($call['name']);
            $span->setTimestamp(intval(round($call['start'])));
            $span->setType(self::SPAN_TYPE);
            $span->setDuration($this->calculateCallDuration($call));

            $spans[] = $span;
        }

        return $spans;
    }

    /**
     * Returns duration of a call in ms
     * @param array $call
     * @return int
     */
    private function calculateCallDuration(array $call): int
    {
        return intval(
            round(($call['end'] - $call['start']) / 1000)
        );
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    private function createSpanNameFromRequest(RequestInterface $request): string
    {
        return sprintf(
            "%s://%s:%s%s",
            $request->getUri()->getScheme(),
            $request->getUri()->getHost(),
            !empty($request->getUri()->getPort()) ? $request->getUri()->getPort() : '<none>',
            $request->getUri()->getPath()
        );
    }

    /**
     * Guzzle middleware for profiling requests
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        $call = [];

        return function (RequestInterface $request, array $options) use ($handler, $call) {
            $call['name'] = $this->createSpanNameFromRequest($request);
            $call['start'] = microtime(true) * 1000000;

            /** @var Promise $promise */
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($call) {
                    $call['end'] = microtime(true) * 1000000;
                    $this->calls[] = $call;
                    return $response;
                }
            );
        };
    }
}
