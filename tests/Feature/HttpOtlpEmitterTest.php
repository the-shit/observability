<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Enums\Severity;
use TheShit\Observability\Enums\SpanKind;
use TheShit\Observability\Tracer;

beforeEach(function () {
    Http::fake();
});

it('posts spans to /v1/traces with the ingestion key header', function () {
    app(Tracer::class)->recordSpan(
        name: 'GitHubPullRequest',
        durationMs: 250,
        success: true,
        traceId: '0af7651916cd43dd-8448eb211c80319c',
        attributes: ['tool.name' => 'GitHubPullRequest'],
        spanId: 'b7ad6b7169203331',
        kind: SpanKind::Client,
    );

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toBe('http://collector.test:4318/v1/traces')
            ->and($request->method())->toBe('POST')
            ->and($request->header('signoz-ingestion-key'))->toBe(['test-key']);

        $span = $request->data()['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

        expect($span['traceId'])->toBe('0af7651916cd43dd8448eb211c80319c')
            ->and($span['spanId'])->toBe('b7ad6b7169203331')
            ->and($span['kind'])->toBe(SpanKind::Client->value)
            ->and($span['status']['code'])->toBe(1);

        return true;
    });
});

it('posts logs to /v1/logs', function () {
    app(Tracer::class)->recordLog('collector ping', ['attempt' => 2], Severity::Warning, 'abc-123');

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toBe('http://collector.test:4318/v1/logs');

        $record = $request->data()['resourceLogs'][0]['scopeLogs'][0]['logRecords'][0];

        expect($record['body']['stringValue'])->toBe('collector ping')
            ->and($record['severityText'])->toBe('warning')
            ->and($record['severityNumber'])->toBe(13)
            ->and($record['traceId'])->toBe('abc123');

        return true;
    });
});

it('stamps every payload with the same resource attribute shape', function () {
    $tracer = app(Tracer::class);
    $tracer->recordSpan('work', 1);
    $tracer->recordLog('note');

    Http::assertSent(function (Request $request): bool {
        $attributes = $request->data()['resourceSpans'][0]['resource']['attributes']
            ?? $request->data()['resourceLogs'][0]['resource']['attributes'];

        expect(array_column($attributes, 'key'))->toBe([
            'service.name',
            'service.version',
            'deployment.environment',
            'telemetry.sdk.name',
            'telemetry.sdk.language',
            'telemetry.sdk.version',
        ])->and($attributes[0]['value']['stringValue'])->toBe('test-app')
            ->and($attributes[1]['value']['stringValue'])->toBe('1.2.3');

        return true;
    });
});

it('trims a trailing slash off the configured endpoint', function () {
    config()->set('observability.otlp.endpoint', 'http://collector.test:4318/');
    app()->forgetInstance(Emitter::class);
    app()->forgetInstance(Tracer::class);

    app(Tracer::class)->recordLog('note');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://collector.test:4318/v1/logs');
});
