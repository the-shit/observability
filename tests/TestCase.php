<?php

declare(strict_types=1);

namespace TheShit\Observability\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TheShit\Observability\ObservabilityServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ObservabilityServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('observability.otlp.ingestion_key', 'test-key');
        $app['config']->set('observability.otlp.endpoint', 'http://collector.test:4318');
        $app['config']->set('observability.service.name', 'test-app');
        $app['config']->set('observability.service.version', '1.2.3');
        $app['config']->set('observability.service.environment', 'testing');
    }
}
