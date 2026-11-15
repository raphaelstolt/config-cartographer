<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Collector;

use ConfigCartographer\Collector\LaravelConfigCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelConfigCollector::class)]
class LaravelConfigCollectorIntegrationTest extends TestCase
{
    public function test_collects_env_references_from_laravel_app(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();

        // Should find DB_HOST, DB_DATABASE, APP_NAME, MAIL_HOST references
        $nodeIds = array_map(static fn($node) => $node->id, $nodes);
        $this->assertContains('env:DB_HOST', $nodeIds);
        $this->assertContains('env:DB_DATABASE', $nodeIds);
        $this->assertContains('env:APP_NAME', $nodeIds);
        $this->assertContains('env:MAIL_HOST', $nodeIds);
    }

    public function test_maps_env_to_config_keys_with_nested_paths(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        $nodeIds = array_map(static fn($node) => $node->id, $nodes);

        // Should find nested config keys like database.connections.mysql.host
        $this->assertContains('config:database.connections.mysql.host', $nodeIds);
        $this->assertContains('config:database.connections.mysql.database', $nodeIds);
        $this->assertContains('config:database.connections.mysql.password', $nodeIds);

        // Should find flat config keys like app.name
        $this->assertContains('config:app.name', $nodeIds);
        $this->assertContains('config:app.debug', $nodeIds);

        // Should find mail config keys
        $this->assertContains('config:mail.mailers.smtp.host', $nodeIds);
        $this->assertContains('config:mail.from.address', $nodeIds);
    }

    public function test_creates_reference_edges(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        $edges = $result->graph->edges;

        // Should have edges mapping config keys to env vars
        $this->assertGreaterThan(0, count($edges));

        // All edges should be References type
        foreach ($edges as $edge) {
            $this->assertEquals('references', $edge->kind->value);
        }
    }

    public function test_no_secret_values_in_output(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        // Secret values from .env should not appear in the graph
        $json = $result->graph->toJson();
        $this->assertStringNotContainsString('canary_laravel_secret_key', $json);
        $this->assertStringNotContainsString('canary_db_password', $json);
    }

    public function test_handles_project_without_config_dir(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/minimal-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        // Should return empty result without errors
        $this->assertCount(0, $result->graph->sortedNodes());
        $this->assertCount(0, $result->diagnostics);
    }

    public function test_source_locations_point_to_config_files(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();

        // Config key nodes should have source locations
        $configNodes = array_filter(
            $nodes,
            static fn($node) => str_starts_with($node->id, 'config:')
        );

        foreach ($configNodes as $node) {
            $this->assertNotNull($node->sourceLocation);
            $this->assertStringContainsString('config/', $node->sourceLocation->file);
            $this->assertGreaterThan(0, $node->sourceLocation->line);
        }
    }

    public function test_db_connection_config_keys(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/laravel-app';
        $collector = new LaravelConfigCollector();
        $result = $collector->collect($fixturePath);

        $nodes = $result->graph->sortedNodes();
        $nodeIds = array_map(static fn($node) => $node->id, $nodes);

        // All MySQL connection config keys should be present
        $this->assertContains('config:database.default', $nodeIds);
        $this->assertContains('config:database.connections.mysql.host', $nodeIds);
        $this->assertContains('config:database.connections.mysql.port', $nodeIds);
        $this->assertContains('config:database.connections.mysql.database', $nodeIds);
        $this->assertContains('config:database.connections.mysql.username', $nodeIds);
        $this->assertContains('config:database.connections.mysql.password', $nodeIds);
        $this->assertContains('config:database.connections.mysql.unix_socket', $nodeIds);
    }
}
