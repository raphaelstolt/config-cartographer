<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Collector;

use ConfigCartographer\Collector\PhpEnvironmentCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpEnvironmentCollector::class)]
class PhpEnvironmentCollectorIntegrationTest extends TestCase
{
    public function test_finds_static_env_references(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';
        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        $nodeIds = array_map(fn($node) => $node->id, $nodes);

        // Should find DB_HOST, DB_PORT, APP_DEBUG references
        $this->assertContains('php:DB_HOST', $nodeIds);
        $this->assertContains('php:DB_PORT', $nodeIds);
        $this->assertContains('php:APP_DEBUG', $nodeIds);
    }

    public function test_marks_dynamic_access_as_unresolved(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/dynamic-php';
        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($fixturePath);

        // Should find static references
        $nodes = $result->graph->sortedNodes();
        $nodeIds = array_map(fn($node) => $node->id, $nodes);
        
        // APP_SECRET is static access
        $this->assertContains('php:APP_SECRET', $nodeIds);
        
        // Dynamic accesses should be marked with Unresolved confidence
        $hasUnresolvedEdge = false;
        foreach ($result->graph->edges as $edge) {
            if ($edge->confidence->value === 'unresolved') {
                $hasUnresolvedEdge = true;
                break;
            }
        }
        $this->assertTrue($hasUnresolvedEdge, 'Should have unresolved edge for dynamic access');
    }

    public function test_handles_parse_errors_gracefully(): void
    {
        // Create a temporary file with invalid PHP
        $tempDir = sys_get_temp_dir() . '/php_collector_test_' . uniqid();
        mkdir($tempDir . '/src', 0777, true);
        
        $invalidPhp = "<?php\n";
        $invalidPhp .= "this is not valid php code {{{\n";
        
        file_put_contents($tempDir . '/src/Invalid.php', $invalidPhp);
        
        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tempDir);
        
        // Should produce diagnostics but not crash
        $this->assertGreaterThan(0, count($result->diagnostics));
        
        // Cleanup
        unlink($tempDir . '/src/Invalid.php');
        rmdir($tempDir . '/src');
        rmdir($tempDir);
    }
}
