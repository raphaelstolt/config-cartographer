<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Collector;

use ConfigCartographer\Collector\SymfonyYamlCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymfonyYamlCollector::class)]
class SymfonyYamlCollectorIntegrationTest extends TestCase
{
    public function test_collects_env_references_from_symfony_app(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/symfony-app';
        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        
        // Should find DATABASE_URL, APP_SECRET, APP_DEBUG references
        $nodeIds = array_map(static fn($node) => $node->id, $nodes);
        $this->assertContains('env:DATABASE_URL', $nodeIds);
        $this->assertContains('env:APP_SECRET', $nodeIds);
        $this->assertContains('env:APP_DEBUG', $nodeIds);
    }

    public function test_maps_env_to_config_keys(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/symfony-app';
        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($fixturePath);

        $edges = $result->graph->edges;
        
        // Should have edges mapping env vars to config keys
        $this->assertGreaterThan(0, count($edges));
        
        // At least one edge should be MapsTo type
        $mapsToEdges = array_filter(
            $edges,
            static fn($edge) => $edge->kind->value === 'maps_to'
        );
        $this->assertGreaterThan(0, count($mapsToEdges));
    }

    public function test_handles_malformed_yaml_gracefully(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/malformed-files';
        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($fixturePath);

        // Should produce diagnostics for malformed files
        $this->assertGreaterThan(0, count($result->diagnostics));
        
        // Should still process valid files
        $nodes = $result->graph->sortedNodes();
        $this->assertGreaterThan(0, count($nodes));
    }

    public function test_valid_files_processed_with_malformed(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/malformed-files';
        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        $nodeIds = array_map(static fn($node) => $node->id, $nodes);
        
        // APP_SECRET from valid.yaml should be found
        $this->assertContains('env:APP_SECRET', $nodeIds);
    }
}
