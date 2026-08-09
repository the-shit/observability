<?php

declare(strict_types=1);

namespace TheShit\Observability\Facades;

use Illuminate\Support\Facades\Facade;
use TheShit\Observability\Tracer;

/**
 * @method static void recordSpan(string $name, int $durationMs, bool $success = true, ?string $traceId = null, array $attributes = [], ?string $errorMessage = null, ?string $spanId = null, ?string $parentSpanId = null, \TheShit\Observability\Enums\SpanKind $kind = \TheShit\Observability\Enums\SpanKind::Internal)
 * @method static mixed measure(string $name, callable $work, array $attributes = [], ?string $traceId = null, ?string $parentSpanId = null, \TheShit\Observability\Enums\SpanKind $kind = \TheShit\Observability\Enums\SpanKind::Internal)
 * @method static void recordLog(string $message, array $context = [], \TheShit\Observability\Enums\Severity|string $severity = \TheShit\Observability\Enums\Severity::Info, ?string $traceId = null)
 *
 * @see Tracer
 */
final class Telemetry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Tracer::class;
    }
}
