<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled — or when no ingestion key is set — the null emitter is
    | bound and instrumented code costs nothing.
    |
    */

    'enabled' => env('OBSERVABILITY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Service identity
    |--------------------------------------------------------------------------
    |
    | `service.name` differs per app; the resource attribute shape is identical
    | across the estate. Leave the version null to have it resolved from the
    | current git SHA, which is what correlates telemetry with deploys.
    |
    */

    'service' => [
        'name' => env('OBSERVABILITY_SERVICE_NAME', env('APP_NAME', 'unknown')),
        'version' => env('OBSERVABILITY_SERVICE_VERSION'),
        'environment' => env('OBSERVABILITY_ENVIRONMENT', env('APP_ENV', 'production')),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTLP transport
    |--------------------------------------------------------------------------
    |
    | The ingestion key belongs to the consuming app and lives only in its own
    | environment — this package never ships or holds credentials. Timeouts are
    | deliberately aggressive: a hung collector must not stall a request.
    |
    */

    'otlp' => [
        'endpoint' => env('OBSERVABILITY_OTLP_ENDPOINT', 'http://localhost:4318'),
        'ingestion_key' => env('OBSERVABILITY_INGESTION_KEY'),
        'ingestion_key_header' => env('OBSERVABILITY_INGESTION_KEY_HEADER', 'signoz-ingestion-key'),
        'connect_timeout' => (int) env('OBSERVABILITY_CONNECT_TIMEOUT', 1),
        'timeout' => (int) env('OBSERVABILITY_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instrumentation scope
    |--------------------------------------------------------------------------
    */

    'scope' => env('OBSERVABILITY_SCOPE', 'the-shit.observability'),

    /*
    |--------------------------------------------------------------------------
    | Redacted attribute keys
    |--------------------------------------------------------------------------
    |
    | Attribute and log-context keys whose values are masked before emission.
    | A trailing `*` makes an entry a prefix match, e.g. `tool.args.*`.
    |
    */

    'redact' => [
        //
    ],

];
