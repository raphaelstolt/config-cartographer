<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Enum for node kinds in the dependency graph.
 */
enum NodeKind: string
{
    case EnvironmentVariable = 'environment_variable';
    case ConfigurationKey = 'configuration_key';
    case PhpReference = 'php_reference';
}
