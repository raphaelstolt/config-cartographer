<?php

declare(strict_types=1);

use ConfigCartographer\Collector\SymfonyYamlCollector;

describe(SymfonyYamlCollector::class, function () {
    it('discovers env references in YAML config files', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp . '/config/packages', 0777, true);
        file_put_contents($tmp . '/config/packages/doctrine.yaml', <<<YAML
doctrine:
    dbal:
        url: '%env(DATABASE_URL)%'
YAML
        );

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(2);

        // Should have DATABASE_URL env node
        $envNode = $result->graph->getNode('env:DATABASE_URL');
        expect($envNode)->not()->toBeNull();

        // Should have a config key node (the heuristic finds the nearest parent key)
        $configNode = $result->graph->getNode('config:dbal');
        expect($configNode)->not()->toBeNull();

        // Cleanup
        array_map('unlink', glob($tmp . '/config/packages/*'));
        rmdir($tmp . '/config/packages');
        rmdir($tmp . '/config');
        rmdir($tmp);
    });

    it('handles env processors like resolve:', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp . '/config/packages', 0777, true);
        file_put_contents($tmp . '/config/packages/doctrine.yaml', <<<YAML
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
YAML
        );

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        $envNode = $result->graph->getNode('env:DATABASE_URL');
        expect($envNode)->not()->toBeNull();

        // Cleanup
        array_map('unlink', glob($tmp . '/config/packages/*'));
        rmdir($tmp . '/config/packages');
        rmdir($tmp . '/config');
        rmdir($tmp);
    });

    it('handles multiple env references in one file', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp . '/config/packages', 0777, true);
        file_put_contents($tmp . '/config/packages/framework.yaml', <<<YAML
framework:
    secret: '%env(APP_SECRET)%'
    router:
        utf8: true
YAML
        );

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        $envNode = $result->graph->getNode('env:APP_SECRET');
        expect($envNode)->not()->toBeNull();

        // Cleanup
        array_map('unlink', glob($tmp . '/config/packages/*'));
        rmdir($tmp . '/config/packages');
        rmdir($tmp . '/config');
        rmdir($tmp);
    });

    it('handles invalid YAML gracefully', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp . '/config/packages', 0777, true);
        file_put_contents($tmp . '/config/packages/broken.yaml', "invalid: yaml: [");

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        // Should have diagnostics but not crash
        expect($result->diagnostics)->toHaveCount(1);
        expect($result->hasErrors())->toBeTrue();

        // Cleanup
        array_map('unlink', glob($tmp . '/config/packages/*'));
        rmdir($tmp . '/config/packages');
        rmdir($tmp . '/config');
        rmdir($tmp);
    });

    it('handles missing config directory', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp);

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        expect($result->nodeCount())->toBe(0);
        rmdir($tmp);
    });

    it('records source locations', function () {
        $tmp = sys_get_temp_dir() . '/cc_yaml_test_' . uniqid();
        mkdir($tmp . '/config/packages', 0777, true);
        file_put_contents($tmp . '/config/packages/doctrine.yaml', <<<YAML
doctrine:
    dbal:
        url: '%env(DATABASE_URL)%'
YAML
        );

        $collector = new SymfonyYamlCollector();
        $result = $collector->collect($tmp);

        $envNode = $result->graph->getNode('env:DATABASE_URL');
        expect($envNode)->not()->toBeNull();
        expect($envNode->sourceLocation)->not()->toBeNull();
        expect($envNode->sourceLocation->line)->toBe(3);

        // Cleanup
        array_map('unlink', glob($tmp . '/config/packages/*'));
        rmdir($tmp . '/config/packages');
        rmdir($tmp . '/config');
        rmdir($tmp);
    });
});
