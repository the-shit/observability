<?php

declare(strict_types=1);

namespace TheShit\Observability;

use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;
use TheShit\Observability\Enums\Severity;
use TheShit\Observability\Enums\SpanKind;
use TheShit\Observability\Support\Redactor;
use Throwable;

/**
 * The call-site API. Instrumented code talks to this and never to OTLP.
 *
 * Every public method is a total function: it returns void and cannot throw,
 * even if the bound emitter misbehaves. That belt-and-braces guard sits here in
 * addition to the emitter's own, so a custom or future emitter cannot silently
 * weaken the contract.
 */
final readonly class Tracer
{
    public function __construct(
        private Emitter $emitter,
        private Redactor $redactor = new Redactor,
    ) {}

    /**
     * Record a completed span. Duration is subtracted from "now" to derive the
     * start time, so call this immediately after the work finishes.
     *
     * @param  array<string, mixed>  $attributes  Scalars keep their type; everything else is JSON-stringified
     */
    public function recordSpan(
        string $name,
        int $durationMs,
        bool $success = true,
        ?string $traceId = null,
        array $attributes = [],
        ?string $errorMessage = null,
        ?string $spanId = null,
        ?string $parentSpanId = null,
        SpanKind $kind = SpanKind::Internal,
    ): void {
        try {
            $this->emitter->emitSpan(Span::completed(
                name: $name,
                durationMs: $durationMs,
                success: $success,
                traceId: $traceId ?? self::newTraceId(),
                spanId: $spanId ?? self::newSpanId(),
                parentSpanId: $parentSpanId,
                kind: $kind,
                attributes: $this->redactor->apply($attributes),
                errorMessage: $errorMessage,
            ));
        } catch (Throwable) {
            // Telemetry must never break the caller.
        }
    }

    /**
     * Time a callable and record it as a span, re-throwing whatever the
     * callable throws once the failure has been recorded.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $work
     * @param  array<string, mixed>  $attributes
     * @return TReturn
     */
    public function measure(
        string $name,
        callable $work,
        array $attributes = [],
        ?string $traceId = null,
        ?string $parentSpanId = null,
        SpanKind $kind = SpanKind::Internal,
    ): mixed {
        $startedAt = microtime(true);

        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->recordSpan(
                name: $name,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                success: false,
                traceId: $traceId,
                attributes: $attributes,
                errorMessage: $e->getMessage(),
                parentSpanId: $parentSpanId,
                kind: $kind,
            );

            throw $e;
        }

        $this->recordSpan(
            name: $name,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            success: true,
            traceId: $traceId,
            attributes: $attributes,
            parentSpanId: $parentSpanId,
            kind: $kind,
        );

        return $result;
    }

    /**
     * Record a structured log line, optionally correlated to a trace.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordLog(
        string $message,
        array $context = [],
        Severity|string $severity = Severity::Info,
        ?string $traceId = null,
    ): void {
        try {
            $this->emitter->emitLog(LogRecord::now(
                message: $message,
                severity: is_string($severity) ? Severity::fromLoose($severity) : $severity,
                traceId: $traceId,
                attributes: $this->redactor->apply($context),
            ));
        } catch (Throwable) {
            // Telemetry must never break the caller.
        }
    }

    /**
     * Generate an OTLP-compatible 8-byte hex span ID.
     */
    public static function newSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Generate an OTLP-compatible 16-byte hex trace ID.
     */
    public static function newTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
