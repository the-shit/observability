<?php

declare(strict_types=1);

use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\ServiceResource;
use TheShit\Observability\Data\Span;
use TheShit\Observability\Enums\Severity;
use TheShit\Observability\Enums\SpanKind;
use TheShit\Observability\Otlp\PayloadFactory;

function payloads(): PayloadFactory
{
    return new PayloadFactory(
        new ServiceResource('quality-runners', 'abc123def456', 'production'),
        'quality-runners.tools',
    );
}

it('encodes a span as OTLP resourceSpans', function () {
    $payload = payloads()->traces(new Span(
        name: 'RunPint',
        traceId: '0af7-6519',
        spanId: 'b7ad6b7169203331',
        startTimeNano: 1_000_000_000,
        endTimeNano: 2_000_000_000,
        success: false,
        parentSpanId: 'aaaaaaaaaaaaaaaa',
        kind: SpanKind::Server,
        attributes: ['repo' => 'asgard'],
        errorMessage: 'pint failed',
    ));

    $span = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

    expect($payload['resourceSpans'][0]['scopeSpans'][0]['scope']['name'])->toBe('quality-runners.tools')
        ->and($span['traceId'])->toBe('0af76519')
        ->and($span['parentSpanId'])->toBe('aaaaaaaaaaaaaaaa')
        ->and($span['kind'])->toBe(2)
        ->and($span['startTimeUnixNano'])->toBe('1000000000')
        ->and($span['endTimeUnixNano'])->toBe('2000000000')
        ->and($span['status'])->toBe(['code' => 2, 'message' => 'pint failed']);
});

it('omits parentSpanId for a root span', function () {
    $payload = payloads()->traces(Span::completed('root', 1, true, 't', 's'));

    expect($payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0])->not->toHaveKey('parentSpanId');
});

it('preserves scalar types when encoding attributes', function () {
    $payload = payloads()->traces(Span::completed('work', 1, true, 't', 's', attributes: [
        'flag' => true,
        'count' => 7,
        'ratio' => 1.5,
        'label' => 'text',
        'nested' => ['a' => 1],
    ]));

    $attributes = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['attributes'];
    $byKey = array_combine(array_column($attributes, 'key'), array_column($attributes, 'value'));

    expect($byKey['success'])->toBe(['boolValue' => true])
        ->and($byKey['flag'])->toBe(['boolValue' => true])
        ->and($byKey['count'])->toBe(['intValue' => '7'])
        ->and($byKey['ratio'])->toBe(['doubleValue' => 1.5])
        ->and($byKey['label'])->toBe(['stringValue' => 'text'])
        ->and($byKey['nested'])->toBe(['stringValue' => '{"a":1}']);
});

it('encodes a log record as OTLP resourceLogs', function () {
    $payload = payloads()->logs(new LogRecord(
        message: 'run finished',
        timestampNano: 1_700_000_000_000_000_000,
        severity: Severity::Error,
        traceId: 'ab-cd',
        attributes: ['exit_code' => 1],
    ));

    $record = $payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'][0];

    expect($record['timeUnixNano'])->toBe('1700000000000000000')
        ->and($record['severityNumber'])->toBe(17)
        ->and($record['severityText'])->toBe('error')
        ->and($record['traceId'])->toBe('abcd')
        ->and($record['attributes'][0])->toBe(['key' => 'log.source', 'value' => ['stringValue' => 'quality-runners']])
        ->and($record['attributes'][1])->toBe(['key' => 'exit_code', 'value' => ['intValue' => '1']]);
});

it('omits traceId on an uncorrelated log record', function () {
    $payload = payloads()->logs(LogRecord::now('orphan'));

    expect($payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'][0])->not->toHaveKey('traceId');
});

it('uses one resource attribute shape for both signals', function () {
    $spanResource = payloads()->traces(Span::completed('work', 1, true, 't', 's'))['resourceSpans'][0]['resource'];
    $logResource = payloads()->logs(LogRecord::now('note'))['resourceLogs'][0]['resource'];

    expect($spanResource)->toBe($logResource)
        ->and($spanResource['attributes'][0])->toBe(['key' => 'service.name', 'value' => ['stringValue' => 'quality-runners']])
        ->and($spanResource['attributes'][1])->toBe(['key' => 'service.version', 'value' => ['stringValue' => 'abc123def456']]);
});
