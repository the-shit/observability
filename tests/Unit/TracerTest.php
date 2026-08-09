<?php

declare(strict_types=1);

use TheShit\Observability\Emitters\FakeEmitter;
use TheShit\Observability\Enums\Severity;
use TheShit\Observability\Enums\SpanKind;
use TheShit\Observability\Support\Redactor;
use TheShit\Observability\Tracer;

/**
 * @return array{FakeEmitter, Tracer}
 */
function tracerWithFake(Redactor $redactor = new Redactor): array
{
    $emitter = new FakeEmitter;

    return [$emitter, new Tracer($emitter, $redactor)];
}

it('hands the emitter a span built from call-site arguments', function () {
    [$emitter, $tracer] = tracerWithFake();

    $tracer->recordSpan(
        name: 'ToolInvocation',
        durationMs: 120,
        success: false,
        traceId: 'trace-1',
        attributes: ['tool.name' => 'Thing'],
        errorMessage: 'exploded',
        spanId: 'span-1',
        parentSpanId: 'parent-1',
        kind: SpanKind::Consumer,
    );

    $span = $emitter->spans[0];

    expect($span->name)->toBe('ToolInvocation')
        ->and($span->success)->toBeFalse()
        ->and($span->errorMessage)->toBe('exploded')
        ->and($span->parentSpanId)->toBe('parent-1')
        ->and($span->kind)->toBe(SpanKind::Consumer)
        ->and($span->attributes)->toBe(['tool.name' => 'Thing'])
        ->and($span->endTimeNano - $span->startTimeNano)->toBe(120 * 1_000_000);
});

it('generates ids when the call site does not supply them', function () {
    [$emitter, $tracer] = tracerWithFake();

    $tracer->recordSpan('work', 1);

    expect($emitter->spans[0]->spanId)->toMatch('/^[0-9a-f]{16}$/')
        ->and($emitter->spans[0]->traceId)->toMatch('/^[0-9a-f]{32}$/');
});

it('normalises a loose severity string', function () {
    [$emitter, $tracer] = tracerWithFake();

    $tracer->recordLog('a', severity: 'warn');
    $tracer->recordLog('b', severity: 'nonsense');

    expect($emitter->logs[0]->severity)->toBe(Severity::Warning)
        ->and($emitter->logs[1]->severity)->toBe(Severity::Info);
});

it('masks redacted attribute keys before they reach the emitter', function () {
    [$emitter, $tracer] = tracerWithFake(new Redactor(['token', 'payload.*']));

    $tracer->recordSpan('work', 1, attributes: [
        'token' => 'sk-live-123',
        'payload.body' => 'secret',
        'TOKEN' => 'sk-live-456',
        'safe' => 'kept',
    ]);

    expect($emitter->spans[0]->attributes)->toBe([
        'token' => '***',
        'payload.body' => '***',
        'TOKEN' => '***',
        'safe' => 'kept',
    ]);
});

it('records a successful measured call and returns its value', function () {
    [$emitter, $tracer] = tracerWithFake();

    $result = $tracer->measure('work', fn (): int => 42, ['queue' => 'default']);

    expect($result)->toBe(42);
    $emitter->assertSpanRecorded('work', fn ($span): bool => $span->success);
});

it('records a failed measured call and rethrows', function () {
    [$emitter, $tracer] = tracerWithFake();

    expect(fn () => $tracer->measure('work', fn () => throw new RuntimeException('nope')))
        ->toThrow(RuntimeException::class, 'nope');

    $span = $emitter->spans[0];

    expect($span->success)->toBeFalse()
        ->and($span->errorMessage)->toBe('nope');
});
