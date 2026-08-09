<?php

declare(strict_types=1);

namespace TheShit\Observability\Support;

/**
 * Tells instrumentation which outbound HTTP traffic is the telemetry pipe
 * itself.
 *
 * Any app that instruments outbound HTTP failures needs this: the emitter
 * speaks HTTP too, so a failing collector would report its own failure, emit
 * again, and recurse until the process dies.
 */
final readonly class CollectorHost
{
    public function __construct(private string $endpoint) {}

    public function matches(string $url): bool
    {
        $host = $this->host($this->endpoint);

        return $host !== null && $host === $this->host($url);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
