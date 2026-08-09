<?php

declare(strict_types=1);

namespace TheShit\Observability\Data;

use TheShit\Observability\Enums\Severity;

/**
 * A structured log line, optionally correlated to a trace.
 */
final readonly class LogRecord
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $message,
        public int $timestampNano,
        public Severity $severity = Severity::Info,
        public ?string $traceId = null,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function now(
        string $message,
        Severity $severity = Severity::Info,
        ?string $traceId = null,
        array $attributes = [],
    ): self {
        return new self(
            message: $message,
            timestampNano: (int) round(microtime(true) * 1_000_000_000),
            severity: $severity,
            traceId: $traceId,
            attributes: $attributes,
        );
    }
}
