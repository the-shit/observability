<?php

declare(strict_types=1);

namespace TheShit\Observability\Emitters;

use Illuminate\Http\Client\Factory as HttpFactory;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;
use TheShit\Observability\Otlp\PayloadFactory;
use Throwable;

/**
 * Fire-and-forget OTLP/HTTP emitter.
 *
 * Timeouts are deliberately aggressive — a hung collector must never stall a
 * request, tool call, or queue worker — and every failure path is swallowed.
 * That swallow-all contract is load-bearing, not laziness: telemetry is never
 * worth an exception in the calling app.
 */
final readonly class HttpOtlpEmitter implements Emitter
{
    public function __construct(
        private HttpFactory $http,
        private PayloadFactory $payloads,
        private string $endpoint,
        private string $ingestionKey,
        private string $ingestionKeyHeader = 'signoz-ingestion-key',
        private int $connectTimeout = 1,
        private int $timeout = 2,
    ) {}

    public function emitSpan(Span $span): void
    {
        $this->send('/v1/traces', fn (): array => $this->payloads->traces($span));
    }

    public function emitLog(LogRecord $record): void
    {
        $this->send('/v1/logs', fn (): array => $this->payloads->logs($record));
    }

    /**
     * Encoding is done inside the try as well, so a value that cannot be
     * serialised is as harmless as a collector that is down.
     *
     * @param  callable(): array<string, mixed>  $payload
     */
    private function send(string $path, callable $payload): void
    {
        try {
            $this->http
                ->withHeaders($this->ingestionKey === '' ? [] : [$this->ingestionKeyHeader => $this->ingestionKey])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post(rtrim($this->endpoint, '/').$path, $payload());
        } catch (Throwable) {
            // Telemetry must never break the caller.
        }
    }
}
