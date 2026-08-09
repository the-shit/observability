<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;
use TheShit\Observability\Enums\Severity;
use TheShit\Observability\Tracer;

/**
 * The load-bearing contract: a broken collector can never throw into the
 * calling app. Verified here rather than asserted by inspection.
 */
$collectorFailures = [
    'connection timeout' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
    'DNS failure' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host: collector.test'),
    'connection refused' => fn () => throw new ConnectionException('cURL error 7: Failed to connect'),
    'collector 500' => fn () => Http::response('upstream error', 500),
    'bad ingestion key (401)' => fn () => Http::response(['error' => 'invalid key'], 401),
    'rate limited (429)' => fn () => Http::response('slow down', 429),
    'garbage response body' => fn () => Http::response("\xB1\x31not json", 200),
    'transport blows up entirely' => fn () => throw new RuntimeException('transport exploded'),
];

it('does not throw when the collector fails while recording a span', function (Closure $failure) {
    Http::fake($failure);

    app(Tracer::class)->recordSpan('tool.call', durationMs: 12, success: true);
})->throwsNoExceptions()->with($collectorFailures);

it('does not throw when the collector fails while recording a log', function (Closure $failure) {
    Http::fake($failure);

    app(Tracer::class)->recordLog('something happened', ['user' => 1], Severity::Error);
})->throwsNoExceptions()->with($collectorFailures);

it('does not throw when the collector fails and the emitter is used directly', function (Closure $failure) {
    Http::fake($failure);

    $emitter = app(Emitter::class);

    $emitter->emitSpan(Span::completed('direct', 5, true, 'trace-1', 'span-1'));
    $emitter->emitLog(LogRecord::now('direct log'));
})->throwsNoExceptions()->with($collectorFailures);

it('returns the callable result even though the collector is down', function () {
    Http::fake(fn () => throw new ConnectionException('collector down'));

    $result = app(Tracer::class)->measure('work', fn (): string => 'done');

    expect($result)->toBe('done');
});

it('still lets the caller own exception surface through measure()', function () {
    Http::fake(fn () => throw new ConnectionException('collector down'));

    expect(fn () => app(Tracer::class)->measure('work', fn () => throw new LogicException('caller bug')))
        ->toThrow(LogicException::class, 'caller bug');
});

it('does not throw when an attribute value cannot be encoded', function () {
    Http::fake();

    // A resource is not JSON-encodable; encoding happens inside the guarded path.
    app(Tracer::class)->recordSpan('tool.call', 1, true, attributes: ['handle' => fopen('php://memory', 'r')]);
})->throwsNoExceptions();

it('does not throw when the tracer is handed an emitter that itself throws', function () {
    $tracer = new Tracer(new class implements Emitter
    {
        public function emitSpan(Span $span): void
        {
            throw new RuntimeException('bad third-party emitter');
        }

        public function emitLog(LogRecord $record): void
        {
            throw new RuntimeException('bad third-party emitter');
        }
    });

    $tracer->recordSpan('tool.call', 3, false, errorMessage: 'boom');
    $tracer->recordLog('a log line');
})->throwsNoExceptions();
