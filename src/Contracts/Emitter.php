<?php

declare(strict_types=1);

namespace TheShit\Observability\Contracts;

use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;

/**
 * The seam between instrumented code and the wire format.
 *
 * Implementations own everything protocol-specific: payload encoding,
 * transport, retries, batching. Call sites hand over domain value objects and
 * never see OTLP, which is what keeps adopting the official OpenTelemetry SDK
 * a matter of binding a different implementation.
 *
 * Contract: implementations MUST NOT throw. Telemetry failure — collector
 * down, bad ingestion key, DNS failure, timeout — can never break the caller.
 */
interface Emitter
{
    public function emitSpan(Span $span): void;

    public function emitLog(LogRecord $record): void;
}
