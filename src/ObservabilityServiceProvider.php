<?php

declare(strict_types=1);

namespace TheShit\Observability;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use TheShit\Observability\Contracts\Emitter;
use TheShit\Observability\Data\ServiceResource;
use TheShit\Observability\Emitters\HttpOtlpEmitter;
use TheShit\Observability\Emitters\NullEmitter;
use TheShit\Observability\Otlp\PayloadFactory;
use TheShit\Observability\Support\CollectorHost;
use TheShit\Observability\Support\Redactor;
use TheShit\Observability\Support\ServiceVersionResolver;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/observability.php', 'observability');

        $this->app->singleton(ServiceResource::class, function ($app): ServiceResource {
            /** @var Config $config */
            $config = $app['config'];

            $version = new ServiceVersionResolver(
                basePath: $this->app->basePath(),
                configured: $this->stringOrNull($config->get('observability.service.version')),
            );

            return new ServiceResource(
                name: (string) $config->get('observability.service.name', 'unknown'),
                version: $version->resolve(),
                environment: (string) $config->get('observability.service.environment', 'production'),
            );
        });

        $this->app->singleton(CollectorHost::class, function ($app): CollectorHost {
            /** @var Config $config */
            $config = $app['config'];

            return new CollectorHost((string) $config->get('observability.otlp.endpoint', ''));
        });

        $this->app->singleton(PayloadFactory::class, function ($app): PayloadFactory {
            /** @var Config $config */
            $config = $app['config'];

            return new PayloadFactory(
                resource: $app->make(ServiceResource::class),
                scopeName: (string) $config->get('observability.scope', 'the-shit.observability'),
            );
        });

        $this->app->singleton(Emitter::class, function ($app): Emitter {
            /** @var Config $config */
            $config = $app['config'];

            $key = $this->stringOrNull($config->get('observability.otlp.ingestion_key'));
            $endpoint = $this->stringOrNull($config->get('observability.otlp.endpoint'));

            // A key alone (SigNoz Cloud) or an explicit endpoint alone
            // (self-hosted collector, which needs no key) is enough to emit.
            // Neither means the app is unconfigured: pay nothing.
            if (! $config->get('observability.enabled', true) || ($key === null && $endpoint === null)) {
                return new NullEmitter;
            }

            return new HttpOtlpEmitter(
                http: $app->make(HttpFactory::class),
                payloads: $app->make(PayloadFactory::class),
                endpoint: $endpoint ?? 'http://localhost:4318',
                ingestionKey: $key ?? '',
                ingestionKeyHeader: (string) $config->get('observability.otlp.ingestion_key_header', 'signoz-ingestion-key'),
                connectTimeout: (int) $config->get('observability.otlp.connect_timeout', 1),
                timeout: (int) $config->get('observability.otlp.timeout', 2),
            );
        });

        $this->app->singleton(Tracer::class, function ($app): Tracer {
            /** @var Config $config */
            $config = $app['config'];

            /** @var list<string> $keys */
            $keys = (array) $config->get('observability.redact', []);

            return new Tracer(
                emitter: $app->make(Emitter::class),
                redactor: new Redactor($keys),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/observability.php' => $this->app->configPath('observability.php'),
            ], 'observability-config');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Emitter::class, Tracer::class, PayloadFactory::class, ServiceResource::class, CollectorHost::class];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
