<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Holds the complete result of an analysis run, including the graph and any diagnostics.
 */
final class AnalysisResult
{
    public function __construct(
        public readonly DependencyGraph $graph,
        public readonly array $diagnostics = []
    ) {
    }

    /**
     * Add a diagnostic to create a new result.
     */
    public function withDiagnostic(Diagnostic $diagnostic): self
    {
        return new self($this->graph, [...$this->diagnostics, $diagnostic]);
    }

    /**
     * Merge another analysis result into this one.
     */
    public function merge(self $other): self
    {
        return new self(
            $this->graph->merge($other->graph),
            [...$this->diagnostics, ...$other->diagnostics]
        );
    }

    /**
     * Get diagnostics by severity.
     * @return array<string, Diagnostic[]>
     */
    public function diagnosticsBySeverity(): array
    {
        $grouped = [];
        foreach ($this->diagnostics as $diagnostic) {
            $severity = $diagnostic->severity->value;
            $grouped[$severity][] = $diagnostic;
        }
        return $grouped;
    }

    /**
     * Check if there are any error diagnostics.
     */
    public function hasErrors(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->severity === DiagnosticSeverity::Error) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the total count of nodes in the graph.
     */
    public function nodeCount(): int
    {
        return count($this->graph->nodes);
    }

    /**
     * Get the total count of edges in the graph.
     */
    public function edgeCount(): int
    {
        return count($this->graph->edges);
    }
}
