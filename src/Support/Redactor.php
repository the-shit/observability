<?php

declare(strict_types=1);

namespace TheShit\Observability\Support;

/**
 * Masks attribute values whose keys the consuming app has declared sensitive.
 *
 * The source tracer hardcoded a list of tool names; the shared package cannot,
 * so each app declares its own keys in `observability.redact`.
 */
final readonly class Redactor
{
    public const string MASK = '***';

    /**
     * @param  list<string>  $keys
     */
    public function __construct(private array $keys = []) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function apply(array $attributes): array
    {
        if ($this->keys === []) {
            return $attributes;
        }

        foreach ($attributes as $key => $value) {
            if ($this->isRedacted((string) $key)) {
                $attributes[$key] = self::MASK;
            }
        }

        return $attributes;
    }

    /**
     * Keys match case-insensitively, and a trailing `*` makes it a prefix match
     * (`payload.*` covers every nested key an app flattens into attributes).
     */
    private function isRedacted(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->keys as $candidate) {
            $candidate = strtolower($candidate);

            if (str_ends_with($candidate, '*')) {
                if (str_starts_with($key, substr($candidate, 0, -1))) {
                    return true;
                }

                continue;
            }

            if ($key === $candidate) {
                return true;
            }
        }

        return false;
    }
}
