<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Immutable directed graph of configuration dependencies.
 * Provides deterministic serialization, deduplication, and cycle-safe traversal.
 */
final class DependencyGraph
{
    /**
     * @param Node[] $nodes All nodes in the graph, indexed by ID
     * @param Edge[] $edges All edges in the graph, indexed by hash
     */
    public function __construct(
        public readonly array $nodes = [],
        public readonly array $edges = []
    ) {
    }

    /**
     * Add a node, deduplicating by ID.
     */
    public function withNode(Node $node): self
    {
        $nodes = $this->nodes;
        if (!isset($nodes[$node->id])) {
            $nodes[$node->id] = $node;
        }
        return new self($nodes, $this->edges);
    }

    /**
     * Add an edge, deduplicating by hash.
     */
    public function withEdge(Edge $edge): self
    {
        $edges = $this->edges;
        $hash = $edge->hash();
        if (!isset($edges[$hash])) {
            $edges[$hash] = $edge;
        }
        return new self($this->nodes, $edges);
    }

    /**
     * Merge another graph into this one, deduplicating nodes and edges.
     */
    public function merge(self $other): self
    {
        $nodes = $this->nodes;
        foreach ($other->nodes as $node) {
            if (!isset($nodes[$node->id])) {
                $nodes[$node->id] = $node;
            }
        }
        $edges = $this->edges;
        foreach ($other->edges as $edge) {
            $hash = $edge->hash();
            if (!isset($edges[$hash])) {
                $edges[$hash] = $edge;
            }
        }
        return new self($nodes, $edges);
    }

    /**
     * Get all nodes sorted deterministically by ID.
     * @return Node[]
     */
    public function sortedNodes(): array
    {
        $nodes = array_values($this->nodes);
        usort($nodes, static fn (Node $a, Node $b) => $a->id <=> $b->id);
        return $nodes;
    }

    /**
     * Get all edges sorted deterministically by hash.
     * @return Edge[]
     */
    public function sortedEdges(): array
    {
        $edges = array_values($this->edges);
        usort($edges, static fn (Edge $a, Edge $b) => $a->hash() <=> $b->hash());
        return $edges;
    }

    /**
     * Get a node by ID, or null if not found.
     */
    public function getNode(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * Get all edges outgoing from a node.
     * @return Edge[]
     */
    public function outgoingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (Edge $e) => $e->sourceId === $nodeId
        ));
    }

    /**
     * Get all edges incoming to a node.
     * @return Edge[]
     */
    public function incomingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (Edge $e) => $e->targetId === $nodeId
        ));
    }

    /**
     * Traverse the graph from a starting node, following outgoing edges.
     * Handles cycles without infinite recursion.
     *
     * @return array<string, Node> visited nodes indexed by ID
     */
    public function traverseFrom(string $startNodeId): array
    {
        $visited = [];
        $queue = [$startNodeId];
        $seen = [$startNodeId => true];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $node = $this->nodes[$currentId] ?? null;
            if ($node !== null) {
                $visited[$currentId] = $node;
            }

            foreach ($this->outgoingEdges($currentId) as $edge) {
                if (!isset($seen[$edge->targetId])) {
                    $seen[$edge->targetId] = true;
                    $queue[] = $edge->targetId;
                }
            }
        }

        return $visited;
    }

    /**
     * Serialize the graph to a deterministic JSON string.
     */
    public function toJson(): string
    {
        $data = [
            'version' => '0.1',
            'nodes' => array_map(
                static fn (Node $n) => [
                    'id' => $n->id,
                    'kind' => $n->kind->value,
                    'name' => $n->name,
                    'source_location' => $n->sourceLocation !== null
                        ? ['file' => $n->sourceLocation->file, 'line' => $n->sourceLocation->line, 'approximate' => $n->sourceLocation->approximate]
                        : null,
                ],
                $this->sortedNodes()
            ),
            'edges' => array_map(
                static fn (Edge $e) => [
                    'source' => $e->sourceId,
                    'target' => $e->targetId,
                    'kind' => $e->kind->value,
                    'confidence' => $e->confidence->value,
                    'evidence' => $e->evidence !== null
                        ? ['file' => $e->evidence->file, 'line' => $e->evidence->line, 'approximate' => $e->evidence->approximate]
                        : null,
                    'description' => $e->description,
                ],
                $this->sortedEdges()
            ),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Count nodes by kind.
     * @return array<string, int>
     */
    public function nodeCountsByKind(): array
    {
        $counts = [
            'environment_variable' => 0,
            'configuration_key' => 0,
            'php_reference' => 0,
        ];
        foreach ($this->nodes as $node) {
            $counts[$node->kind->value]++;
        }
        return $counts;
    }

    /**
     * Count edges by kind.
     * @return array<string, int>
     */
    public function edgeCountsByKind(): array
    {
        $counts = [];
        foreach ($this->edges as $edge) {
            $kind = $edge->kind->value;
            $counts[$kind] = ($counts[$kind] ?? 0) + 1;
        }
        return $counts;
    }
}
