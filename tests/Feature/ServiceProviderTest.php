<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\ServiceResource;
use TheShit\Observability\Emitters\HttpOtlpEmitter;
use TheShit\Observability\Emitters\NullEmitter;
use TheShit\Observability\Facades\Telemetry;
use TheShit\Observability\ObservabilityServiceProvider;
use TheShit\Observability\Tracer;

it('binds the http emitter when an ingestion key is configured', function () {
    expect(app(Emitter::class))->toBeInstanceOf(HttpOtlpEmitter::class);
});

it('falls back to the null emitter when no ingestion key is configured', function () {
    config()->set('observability.otlp.ingestion_key', null);
    app()->forgetInstance(Emitter::class);

    expect(app(Emitter::class))->toBeInstanceOf(NullEmitter::class);
});

it('falls back to the null emitter when telemetry is disabled', function () {
    config()->set('observability.enabled', false);
    app()->forgetInstance(Emitter::class);

    expect(app(Emitter::class))->toBeInstanceOf(NullEmitter::class);
});

it('sends nothing when telemetry is disabled', function () {
    Http::fake();
    config()->set('observability.enabled', false);
    app()->forgetInstance(Emitter::class);
    app()->forgetInstance(Tracer::class);

    app(Tracer::class)->recordSpan('work', 1);

    Http::assertNothingSent();
});

it('builds the service resource from the consuming app config', function () {
    $resource = app(ServiceResource::class);

    expect($resource->name)->toBe('test-app')
        ->and($resource->version)->toBe('1.2.3')
        ->and($resource->environment)->toBe('testing');
});

it('resolves the service version from git when none is configured', function () {
    config()->set('observability.service.version', null);
    app()->forgetInstance(ServiceResource::class);

    expect(app(ServiceResource::class)->version)->toMatch('/^[0-9a-f]{12}$|^unknown$/');
});

it('applies the redact list configured by the consuming app', function () {
    Http::fake();
    config()->set('observability.redact', ['tool.args']);
    app()->forgetInstance(Tracer::class);

    app(Tracer::class)->recordSpan('work', 1, attributes: ['tool.args' => 'super secret', 'tool.name' => 'Thing']);

    Http::assertSent(function ($request): bool {
        $attributes = $request->data()['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['attributes'];
        $byKey = array_combine(array_column($attributes, 'key'), array_column($attributes, 'value'));

        expect($byKey['tool.args']['stringValue'])->toBe('***')
            ->and($byKey['tool.name']['stringValue'])->toBe('Thing');

        return true;
    });
});

it('exposes the tracer through the Telemetry facade', function () {
    Http::fake();

    Telemetry::recordLog('via facade');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/v1/logs'));
});

it('publishes its config so consuming apps own the values', function () {
    $paths = ServiceProvider::pathsToPublish(
        ObservabilityServiceProvider::class,
        'observability-config',
    );

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toEndWith('config/observability.php');
});

it('ships no credentials of its own', function () {
    $config = require __DIR__.'/../../config/observability.php';

    expect($config['otlp']['ingestion_key'])->toBeNull();
});
