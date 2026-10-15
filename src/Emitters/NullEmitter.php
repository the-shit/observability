<?php

declare(strict_types=1);

namespace TheShit\Observability\Emitters;

use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;

/**
 * Discards everything. Bound automatically when telemetry is disabled or neither an
 * ingestion key nor an endpoint is configured, so unconfigured apps pay nothing.
 */
final class NullEmitter implements Emitter
{
    public function emitSpan(Span $span): void
    {
        //
    }

    public function emitLog(LogRecord $record): void
    {
        //
    }
}
