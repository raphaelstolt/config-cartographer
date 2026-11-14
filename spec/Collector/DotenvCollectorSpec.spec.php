<?php

declare(strict_types=1);

use ConfigCartographer\Collector\DotenvCollector;

$canarySecret = 'CANARY_SECRET_VALUE_DO_NOT_LEAK_12345';
$canaryPassword = 'CANARY_PASSWORD_SUPER_SECRET_67890';

describe(DotenvCollector::class, function () use ($canarySecret, $canaryPassword) {
    it('discovers .env files and extracts key names', function () use ($canarySecret) {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "DATABASE_URL=postgres://localhost\nAPP_SECRET=hidden\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(2);
        expect($nodes[0]->name)->toBe('APP_SECRET');
        expect($nodes[1]->name)->toBe('DATABASE_URL');

        $json = $result->graph->toJson();
        expect($json)->not()->toContain($canarySecret);

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('handles comments and blank lines', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "# This is a comment\n\nAPP_NAME=Test\n# Another comment\nAPP_DEBUG=true\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(2);

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('handles export prefix', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "export API_KEY=key123\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(1);
        expect($nodes[0]->name)->toBe('API_KEY');

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('handles quoted values', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "DB_HOST=\"localhost\"\nDB_PORT='3306'\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(2);

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('handles duplicate keys across files', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "APP_NAME=prod\n");
        file_put_contents($tmp . '/.env.example', "APP_NAME=example\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $nodes = $result->graph->sortedNodes();
        expect($nodes)->toHaveCount(1);
        expect($nodes[0]->name)->toBe('APP_NAME');

        unlink($tmp . '/.env');
        unlink($tmp . '/.env.example');
        rmdir($tmp);
    });

    it('never exposes secret values in output', function () use ($canarySecret, $canaryPassword) {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "SECRET_KEY={$canarySecret}\nPASSWORD={$canaryPassword}\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $json = $result->graph->toJson();
        expect($json)->not()->toContain($canarySecret);
        expect($json)->not()->toContain($canaryPassword);

        foreach ($result->diagnostics as $diag) {
            $diagStr = (string) $diag;
            expect($diagStr)->not()->toContain($canarySecret);
            expect($diagStr)->not()->toContain($canaryPassword);
        }

        foreach ($result->graph->sortedNodes() as $node) {
            expect($node->name)->not()->toContain($canarySecret);
        }

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('records source locations', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);
        file_put_contents($tmp . '/.env', "# Comment\nDATABASE_URL=postgres://localhost\nAPP_SECRET=hidden\n");

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        $dbNode = $result->graph->getNode('env:DATABASE_URL');
        expect($dbNode)->not()->toBeNull();
        expect($dbNode->sourceLocation)->not()->toBeNull();
        expect($dbNode->sourceLocation->line)->toBe(2);

        unlink($tmp . '/.env');
        rmdir($tmp);
    });

    it('handles empty directory gracefully', function () {
        $tmp = sys_get_temp_dir() . '/cc_dotenv_test_' . uniqid();
        mkdir($tmp);

        $collector = new DotenvCollector();
        $result = $collector->collect($tmp);

        expect($result->nodeCount())->toBe(0);
        rmdir($tmp);
    });
});
