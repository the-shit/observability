<?php

declare(strict_types=1);

namespace TheShit\Observability\Otlp;

use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\ServiceResource;
use TheShit\Observability\Data\Span;

/**
 * Hand-builds the OTLP/HTTP JSON payloads for /v1/traces and /v1/logs.
 *
 * This is the only class in the package that knows the wire format. It is
 * deliberately dependency-free so it stays cheap and so replacing it with the
 * official SDK is a single-class swap behind {@see Emitter}.
 */
final readonly class PayloadFactory
{
    public function __construct(
        private ServiceResource $resource,
        private string $scopeName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function traces(Span $span): array
    {
        $attributes = [
            ['key' => 'success', 'value' => ['boolValue' => $span->success]],
        ];

        foreach ($span->attributes as $key => $value) {
            $attributes[] = ['key' => (string) $key, 'value' => $this->anyValue($value)];
        }

        $encoded = [
            'traceId' => $this->hex($span->traceId),
            'spanId' => $span->spanId,
            'name' => $span->name,
            'kind' => $span->kind->value,
            'startTimeUnixNano' => (string) $span->startTimeNano,
            'endTimeUnixNano' => (string) $span->endTimeNano,
            'attributes' => $attributes,
            'status' => [
                'code' => $span->success ? 1 : 2,
                'message' => $span->errorMessage ?? '',
            ],
        ];

        if ($span->parentSpanId !== null) {
            $encoded['parentSpanId'] = $span->parentSpanId;
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => ['attributes' => $this->resourceAttributes()],
                    'scopeSpans' => [
                        [
                            'scope' => ['name' => $this->scopeName],
                            'spans' => [$encoded],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function logs(LogRecord $record): array
    {
        $encoded = [
            'timeUnixNano' => (string) $record->timestampNano,
            'severityNumber' => $record->severity->otlpNumber(),
            'severityText' => $record->severity->value,
            'body' => ['stringValue' => $record->message],
            'attributes' => [
                ['key' => 'log.source', 'value' => ['stringValue' => $this->resource->name]],
            ],
        ];

        if ($record->traceId !== null) {
            $encoded['traceId'] = $this->hex($record->traceId);
        }

        foreach ($record->attributes as $key => $value) {
            $encoded['attributes'][] = ['key' => (string) $key, 'value' => $this->anyValue($value)];
        }

        return [
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => $this->resourceAttributes()],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => $this->scopeName],
                            'logRecords' => [$encoded],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Resource attributes shared by every span and log record. Identical shape
     * across the estate; only the values differ per app.
     *
     * @return array<int, array{key: string, value: array<string, mixed>}>
     */
    private function resourceAttributes(): array
    {
        return [
            ['key' => 'service.name', 'value' => ['stringValue' => $this->resource->name]],
            ['key' => 'service.version', 'value' => ['stringValue' => $this->resource->version]],
            ['key' => 'deployment.environment', 'value' => ['stringValue' => $this->resource->environment]],
            ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'opentelemetry']],
            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
            ['key' => 'telemetry.sdk.version', 'value' => ['stringValue' => '1.0.0']],
        ];
    }

    /**
     * Map a PHP value onto an OTLP AnyValue, preserving scalar types.
     *
     * @return array<string, mixed>
     */
    private function anyValue(mixed $value): array
    {
        return match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            default => ['stringValue' => $this->stringify($value)],
        };
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * OTLP wants bare hex, so UUID-style trace IDs lose their dashes.
     */
    private function hex(string $traceId): string
    {
        return str_replace('-', '', $traceId);
    }
}
