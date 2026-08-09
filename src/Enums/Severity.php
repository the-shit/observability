<?php

declare(strict_types=1);

namespace TheShit\Observability\Enums;

/**
 * Log severities the estate actually uses, mapped onto the OTLP severity
 * numbers — https://opentelemetry.io/docs/specs/otel/logs/data-model/#severity-fields
 */
enum Severity: string
{
    case Debug = 'debug';

    case Info = 'info';

    case Warning = 'warning';

    case Error = 'error';

    /**
     * Resolve a loose severity string (PSR-3 style) onto the enum, defaulting
     * to Info so an unrecognised level never costs the caller a log line.
     */
    public static function fromLoose(string $severity): self
    {
        return match (strtolower($severity)) {
            'debug', 'trace' => self::Debug,
            'warn', 'warning' => self::Warning,
            'error', 'err', 'critical', 'alert', 'emergency' => self::Error,
            default => self::Info,
        };
    }

    public function otlpNumber(): int
    {
        return match ($this) {
            self::Debug => 5,
            self::Info => 9,
            self::Warning => 13,
            self::Error => 17,
        };
    }
}
