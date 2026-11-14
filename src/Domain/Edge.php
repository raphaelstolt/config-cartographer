<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Immutable edge representing a relationship between two nodes in the dependency graph.
 * Every edge carries evidence (source location) and a confidence level.
 */
final class Edge
{
    /**
     * @param string $sourceId The ID of the source node
     * @param string $targetId The ID of the target node
     * @param EdgeKind $kind The type of relationship
     * @param Confidence $confidence How certain we are about this relationship
     * @param SourceLocation|null $evidence Source location where this relationship was discovered
     * @param string|null $description Optional human-readable description of the relationship
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $targetId,
        public readonly EdgeKind $kind,
        public readonly Confidence $confidence,
        public readonly ?SourceLocation $evidence = null,
        public readonly ?string $description = null
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->sourceId === $other->sourceId
            && $this->targetId === $other->targetId
            && $this->kind === $other->kind;
    }

    /**
     * Generate a deterministic hash for deduplication.
     */
    public function hash(): string
    {
        return hash('xxh128', $this->sourceId . '|' . $this->targetId . '|' . $this->kind->value);
    }
}
