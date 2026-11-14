<?php

declare(strict_types=1);

use ConfigCartographer\Collector\PhpEnvironmentCollector;

describe(PhpEnvironmentCollector::class, function () {
    it('detects getenv() calls', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/app.php', '<?php
$dbUrl = getenv("DATABASE_URL");
$secret = getenv("APP_SECRET");
');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(4); // 2 env + 2 php reference nodes

        $dbNode = $result->graph->getNode('env:DATABASE_URL');
        expect($dbNode)->not()->toBeNull();

        // Cleanup
        unlink($tmp . '/app.php');
        rmdir($tmp);
    });

    it('detects $_ENV[] access', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/app.php', '<?php
$host = $_ENV["DB_HOST"];
');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        $envNode = $result->graph->getNode('env:DB_HOST');
        expect($envNode)->not()->toBeNull();

        // Cleanup
        unlink($tmp . '/app.php');
        rmdir($tmp);
    });

    it('detects $_SERVER[] access', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/app.php', '<?php
$host = $_SERVER["HTTP_HOST"];
');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        $serverNode = $result->graph->getNode('env:HTTP_HOST');
        expect($serverNode)->not()->toBeNull();

        // Cleanup
        unlink($tmp . '/app.php');
        rmdir($tmp);
    });

    it('marks dynamic accesses as unresolved', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/app.php', '<?php
$key = "DATABASE_URL";
$val = getenv($key);
');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        // Should have unresolved edges
        $edges = $result->graph->sortedEdges();
        $hasUnresolved = false;
        foreach ($edges as $edge) {
            if ($edge->confidence->value === 'unresolved') {
                $hasUnresolved = true;
                break;
            }
        }
        expect($hasUnresolved)->toBeTrue();

        // Cleanup
        unlink($tmp . '/app.php');
        rmdir($tmp);
    });

    it('handles parse errors gracefully', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/broken.php', '<?php this is not valid php {{{');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        // Should have a warning diagnostic
        expect($result->diagnostics)->toHaveCount(1);

        // Cleanup
        unlink($tmp . '/broken.php');
        rmdir($tmp);
    });

    it('records source locations', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/app.php', '<?php
// line 2
$dbUrl = getenv("DATABASE_URL");
');

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        $phpNode = $result->graph->getNode('php:DATABASE_URL');
        expect($phpNode)->not()->toBeNull();
        expect($phpNode->sourceLocation)->not()->toBeNull();
        expect($phpNode->sourceLocation->line)->toBe(3);

        // Cleanup
        unlink($tmp . '/app.php');
        rmdir($tmp);
    });

    it('handles empty directory', function () {
        $tmp = sys_get_temp_dir() . '/cc_php_test_' . uniqid();
        mkdir($tmp);

        $collector = new PhpEnvironmentCollector();
        $result = $collector->collect($tmp);

        expect($result->nodeCount())->toBe(0);
        rmdir($tmp);
    });
});
