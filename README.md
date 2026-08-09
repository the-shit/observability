# the-shit/observability

One SigNoz/OTLP wiring for the estate: identical resource-attribute shape everywhere, `service.name` per app, and a hard contract that **telemetry failure never throws into the calling app**.

Extracted from `lexi-agent`'s `SigNozTracer` (three weeks of production data) per [Asgard#15](https://github.com/jordanpartridge/Asgard/issues/15).

## Install

Private package. Until a private registry exists, add a path or VCS repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../observability" }
    ],
    "require": {
        "the-shit/observability": "*"
    }
}
```

Publish the config:

```bash
php artisan vendor:publish --tag=observability-config
```

## Configure

The package holds no credentials. Each app sets its own:

```dotenv
OBSERVABILITY_SERVICE_NAME=quality-runners
OBSERVABILITY_OTLP_ENDPOINT=https://ingest.us.signoz.cloud:443
OBSERVABILITY_INGESTION_KEY=...
```

With no ingestion key, or with `OBSERVABILITY_ENABLED=false`, the null emitter is bound and instrumented code costs nothing.

`service.version` resolves to the configured value, else the current git SHA (short), else `unknown` — that is what correlates telemetry with deploys.

## Use

```php
use TheShit\Observability\Tracer;

public function __construct(private Tracer $telemetry) {}

$this->telemetry->recordSpan(
    name: 'ToolInvocationAudited',
    durationMs: 142,
    success: true,
    traceId: $traceId,
    attributes: ['tool.name' => $tool, 'agent.name' => $agent],
);

$result = $this->telemetry->measure('RunPint', fn () => $this->pint->run($repo));

$this->telemetry->recordLog('grant denied', ['tool' => $tool], Severity::Warning, $traceId);
```

Nothing above mentions OTLP. Every method returns void and cannot throw.

## Swapping the emitter

Call sites depend on `Tracer`; `Tracer` depends on `TheShit\Observability\Contracts\Emitter`:

```php
interface Emitter
{
    public function emitSpan(Span $span): void;
    public function emitLog(LogRecord $record): void;
}
```

Shipped implementations: `HttpOtlpEmitter` (real), `NullEmitter` (disabled), `FakeEmitter` (tests). Adopting the official OpenTelemetry SDK later means binding a different `Emitter` — no instrumented line changes.

In tests:

```php
$fake = new FakeEmitter;
app()->instance(Emitter::class, $fake);
app()->forgetInstance(Tracer::class);

// ...

$fake->assertSpanRecorded('ToolInvocationAudited');
```

## Redaction

```php
'redact' => ['tool.args', 'payload.*'],
```

Matching attribute and log-context keys are masked with `***` before emission. Matching is case-insensitive; a trailing `*` is a prefix match.

## Quality

```bash
composer test      # pest
composer lint      # pint
composer analyse   # phpstan level 5 + larastan
```
