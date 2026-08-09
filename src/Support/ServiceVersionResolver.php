<?php

declare(strict_types=1);

namespace TheShit\Observability\Support;

use Throwable;

/**
 * Resolves the deployed version stamped onto every span and log record, which
 * is what makes telemetry correlatable with deploys.
 *
 * Explicit configured version wins; otherwise the current git SHA (short) read
 * straight off `.git`, so no build step or shell-out is required. Resolved once
 * per instance.
 */
final class ServiceVersionResolver
{
    private ?string $resolved = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?string $configured = null,
    ) {}

    public function resolve(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if (is_string($this->configured) && $this->configured !== '') {
            return $this->resolved = $this->configured;
        }

        return $this->resolved = $this->gitSha() ?? 'unknown';
    }

    private function gitSha(): ?string
    {
        try {
            $gitDir = rtrim($this->basePath, '/').'/.git';
            $head = $gitDir.'/HEAD';

            if (! is_file($head)) {
                return null;
            }

            $contents = trim((string) file_get_contents($head));

            if (str_starts_with($contents, 'ref: ')) {
                $refPath = $gitDir.'/'.substr($contents, 5);

                if (! is_file($refPath)) {
                    return $this->packedRef($gitDir, substr($contents, 5));
                }

                $contents = trim((string) file_get_contents($refPath));
            }

            return $contents !== '' ? substr($contents, 0, 12) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A freshly cloned or gc'd repo keeps refs in `.git/packed-refs` rather
     * than as loose files, which would otherwise read as "unknown".
     */
    private function packedRef(string $gitDir, string $ref): ?string
    {
        $packed = $gitDir.'/packed-refs';

        if (! is_file($packed)) {
            return null;
        }

        foreach (explode("\n", (string) file_get_contents($packed)) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) === 2 && $parts[1] === $ref) {
                return substr($parts[0], 0, 12);
            }
        }

        return null;
    }
}
