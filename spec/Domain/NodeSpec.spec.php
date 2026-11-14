<?php

declare(strict_types=1);

use ConfigCartographer\Domain\Node;
use ConfigCartographer\Domain\NodeKind;
use ConfigCartographer\Domain\SourceLocation;

describe(Node::class, function () {
    it('creates an environment variable node', function () {
        $node = Node::environmentVariable('DATABASE_URL');
        expect($node->id)->toBe('env:DATABASE_URL');
        expect($node->kind)->toBe(NodeKind::EnvironmentVariable);
        expect($node->name)->toBe('DATABASE_URL');
        expect($node->sourceLocation)->toBeNull();
    });

    it('creates a configuration key node', function () {
        $node = Node::configurationKey('doctrine.dbal.url');
        expect($node->id)->toBe('config:doctrine.dbal.url');
        expect($node->kind)->toBe(NodeKind::ConfigurationKey);
        expect($node->name)->toBe('doctrine.dbal.url');
    });

    it('creates a php reference node', function () {
        $node = Node::phpReference('APP_SECRET');
        expect($node->id)->toBe('php:APP_SECRET');
        expect($node->kind)->toBe(NodeKind::PhpReference);
        expect($node->name)->toBe('APP_SECRET');
    });

    it('stores source location', function () {
        $location = new SourceLocation('.env', 5);
        $node = Node::environmentVariable('DATABASE_URL', $location);
        expect($node->sourceLocation)->toBe($location);
    });

    it('provides identity equality', function () {
        $node1 = Node::environmentVariable('DATABASE_URL');
        $node2 = Node::environmentVariable('DATABASE_URL');
        $node3 = Node::environmentVariable('APP_SECRET');

        expect($node1->equals($node2))->toBeTrue();
        expect($node1->equals($node3))->toBeFalse();
    });

    it('generates deterministic hash', function () {
        $node1 = Node::environmentVariable('DATABASE_URL');
        $node2 = Node::environmentVariable('DATABASE_URL');

        expect($node1->hash())->toBe($node2->hash());
    });
});
