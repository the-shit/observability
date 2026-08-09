<?php

declare(strict_types=1);

namespace TheShit\Observability\Enums;

/**
 * OTLP span kinds — https://opentelemetry.io/docs/specs/otel/trace/api/#spankind
 *
 * Call sites use this enum rather than the raw integer so the OTLP numbering
 * stays an emitter concern.
 */
enum SpanKind: int
{
    case Internal = 1;

    case Server = 2;

    case Client = 3;

    case Producer = 4;

    case Consumer = 5;
}
