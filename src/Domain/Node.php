<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Immutable node in the dependency graph.
 * Each node represents a configuration element with a stable ID and source location.
 */
final class Node
{
    /**
     * @param string $id Stable, unique identifier (e.g., "env:DATABASE_URL", "config:doctrine.dbal.url")
     * @param NodeKind $kind The type of configuration element
     * @param string $name Human-readable name (e.g., "DATABASE_URL", "doctrine.dbal.url")
     * @param SourceLocation|null $sourceLocation Where this node was discovered
     */
    public function __construct(
        public readonly string $id,
        public readonly NodeKind $kind,
        public readonly string $name,
        public readonly ?SourceLocation $sourceLocation = null
    ) {
    }

    /**
     * Create an environment variable node.
     */
    public static function environmentVariable(string $name, ?SourceLocation $sourceLocation = null): self
    {
        return new self("env:{$name}", NodeKind::EnvironmentVariable, $name, $sourceLocation);
    }

    /**
     * Create a configuration key node.
     */
    public static function configurationKey(string $path, ?SourceLocation $sourceLocation = null): self
    {
        return new self("config:{$path}", NodeKind::ConfigurationKey, $path, $sourceLocation);
    }

    /**
     * Create a PHP reference node.
     */
    public static function phpReference(string $name, ?SourceLocation $sourceLocation = null): self
    {
        return new self("php:{$name}", NodeKind::PhpReference, $name, $sourceLocation);
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    /**
     * Generate a deterministic hash for deduplication.
     */
    public function hash(): string
    {
        return hash('xxh128', $this->id . '|' . $this->kind->value . '|' . $this->name);
    }
}
