<?php

declare(strict_types=1);

namespace TheShit\Observability\Emitters;

use PHPUnit\Framework\Assert;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\LogRecord;
use TheShit\Observability\Data\Span;

/**
 * Records what was emitted so consuming apps can assert on their own
 * instrumentation without standing up a collector.
 */
final class FakeEmitter implements Emitter
{
    /** @var list<Span> */
    public array $spans = [];

    /** @var list<LogRecord> */
    public array $logs = [];

    public function emitSpan(Span $span): void
    {
        $this->spans[] = $span;
    }

    public function emitLog(LogRecord $record): void
    {
        $this->logs[] = $record;
    }

    /**
     * @param  null|callable(Span): bool  $filter
     */
    public function assertSpanRecorded(string $name, ?callable $filter = null): void
    {
        $matches = array_filter(
            $this->spans,
            fn (Span $span): bool => $span->name === $name && ($filter === null || $filter($span)),
        );

        Assert::assertNotEmpty($matches, "No span named [{$name}] was emitted.");
    }

    public function assertNothingRecorded(): void
    {
        Assert::assertSame([], $this->spans, 'Expected no spans to be emitted.');
        Assert::assertSame([], $this->logs, 'Expected no logs to be emitted.');
    }

    public function flush(): void
    {
        $this->spans = [];
        $this->logs = [];
    }
}
