<?php

declare(strict_types=1);

namespace TheShit\Observability\Data;

use TheShit\Observability\Enums\SpanKind;

/**
 * A completed unit of work, in domain terms. Carries no OTLP structure —
 * encoding it is the emitter's job.
 */
final readonly class Span
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $name,
        public string $traceId,
        public string $spanId,
        public int $startTimeNano,
        public int $endTimeNano,
        public bool $success = true,
        public ?string $parentSpanId = null,
        public SpanKind $kind = SpanKind::Internal,
        public array $attributes = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * Build a span for work that has just finished, deriving the start time by
     * subtracting the duration from "now".
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function completed(
        string $name,
        int $durationMs,
        bool $success,
        string $traceId,
        string $spanId,
        ?string $parentSpanId = null,
        SpanKind $kind = SpanKind::Internal,
        array $attributes = [],
        ?string $errorMessage = null,
    ): self {
        $endTimeNano = (int) round(microtime(true) * 1_000_000_000);

        return new self(
            name: $name,
            traceId: $traceId,
            spanId: $spanId,
            startTimeNano: $endTimeNano - ($durationMs * 1_000_000),
            endTimeNano: $endTimeNano,
            success: $success,
            parentSpanId: $parentSpanId,
            kind: $kind,
            attributes: $attributes,
            errorMessage: $errorMessage,
        );
    }
}
