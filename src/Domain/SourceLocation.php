<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Immutable value object representing a source code location.
 */
final class SourceLocation
{
    /**
     * @param string $file File path (relative to project root)
     * @param int|null $line Line number (1-based), null if unknown
     * @param bool $approximate True if the line number is approximate rather than exact
     */
    public function __construct(
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly bool $approximate = false
    ) {
    }

    /**
     * Create a SourceLocation from an absolute path, making it relative to the project root.
     */
    public static function fromAbsolute(string $absolutePath, ?int $line = null, bool $approximate = false): self
    {
        return new self($absolutePath, $line, $approximate);
    }

    public function __toString(): string
    {
        $base = $this->file;
        if ($this->line !== null) {
            $base .= ':' . $this->line;
            if ($this->approximate) {
                $base .= '~';
            }
        }
        return $base;
    }

    public function equals(self $other): bool
    {
        return $this->file === $other->file
            && $this->line === $other->line
            && $this->approximate === $other->approximate;
    }
}
