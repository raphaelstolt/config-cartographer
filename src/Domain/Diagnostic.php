<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * A diagnostic message produced during analysis, such as a warning or error.
 */
final class Diagnostic
{
    public function __construct(
        public readonly string $message,
        public readonly DiagnosticSeverity $severity,
        public readonly ?SourceLocation $sourceLocation = null
    ) {
    }

    public function __toString(): string
    {
        $prefix = match ($this->severity) {
            DiagnosticSeverity::Error => '[ERROR]',
            DiagnosticSeverity::Warning => '[WARNING]',
            DiagnosticSeverity::Info => '[INFO]',
        };

        $location = $this->sourceLocation !== null ? " at {$this->sourceLocation}" : '';
        return "{$prefix}{$location}: {$this->message}";
    }
}
