<?php

declare(strict_types=1);

namespace TheShit\Observability\Data;

/**
 * The identity every span and log record from this app is stamped with.
 *
 * Built once from the consuming app's own config so the resource attribute
 * shape is identical across the estate while `service.name` differs per app.
 */
final readonly class ServiceResource
{
    public function __construct(
        public string $name,
        public string $version,
        public string $environment,
    ) {}
}
