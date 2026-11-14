<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Enum for edge kinds representing relationship types between nodes.
 */
enum EdgeKind: string
{
    case DeclaredIn = 'declared_in';
    case References = 'references';
    case MapsTo = 'maps_to';
}
