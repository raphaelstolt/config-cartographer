<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Collector;

use ConfigCartographer\Collector\DotenvCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotenvCollector::class)]
class DotenvCollectorIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up any temporary directories created during tests
    }

    public function test_collects_env_file_from_minimal_app(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';
        $collector = new DotenvCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        
        // Should find DATABASE_URL, APP_SECRET, APP_DEBUG
        $this->assertGreaterThanOrEqual(3, count($nodes));
        
        $nodeIds = array_map(fn($node) => $node->id, $nodes);
        $this->assertContains('env:DATABASE_URL', $nodeIds);
        $this->assertContains('env:APP_SECRET', $nodeIds);
        $this->assertContains('env:APP_DEBUG', $nodeIds);
    }

    public function test_secret_values_never_appear_in_output(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';
        $collector = new DotenvCollector();
        $result = $collector->collect($fixturePath);

        // Get the JSON output
        $json = $result->graph->toJson();
        
        // Canary secret values should never appear
        $this->assertStringNotContainsString('canary_secret_do_notExpose', $json);
        $this->assertStringNotContainsString('postgres://localhost', $json);
    }

    public function test_handles_duplicate_keys(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/duplicate-env';
        $collector = new DotenvCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        
        // Should handle duplicates - DATABASE_URL appears twice but should be deduplicated
        $databaseUrlNodes = array_filter(
            $nodes,
            fn($node) => $node->id === 'env:DATABASE_URL'
        );
        
        // Only one node per unique ID
        $this->assertCount(1, $databaseUrlNodes);
    }

    public function test_diagnostics_do_not_leak_secrets(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';
        $collector = new DotenvCollector();
        $result = $collector->collect($fixturePath);

        // Even if no diagnostics, we should verify the graph doesn't leak secrets
        $json = $result->graph->toJson();
        $this->assertStringNotContainsString('canary_secret_do_notExpose', $json);
        
        // Check diagnostics if any exist
        foreach ($result->diagnostics as $diagnostic) {
            $message = (string) $diagnostic;
            $this->assertStringNotContainsString('canary_secret_do_notExpose', $message);
        }
    }
}
