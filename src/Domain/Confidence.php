<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Enum for confidence levels of relationships in the graph.
 */
enum Confidence: string
{
    case Observed = 'observed';
    case Inferred = 'inferred';
    case Unresolved = 'unresolved';
}
