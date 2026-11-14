<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Severity levels for diagnostic messages.
 */
enum DiagnosticSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
