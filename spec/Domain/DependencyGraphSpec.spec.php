<?php

declare(strict_types=1);

use ConfigCartographer\Domain\Confidence;
use ConfigCartographer\Domain\DependencyGraph;
use ConfigCartographer\Domain\Edge;
use ConfigCartographer\Domain\EdgeKind;
use ConfigCartographer\Domain\Node;
use ConfigCartographer\Domain\SourceLocation;

describe(DependencyGraph::class, function () {
    it('starts empty', function () {
        $graph = new DependencyGraph();
        expect($graph->nodes)->toBe([]);
        expect($graph->edges)->toBe([]);
    });

    it('adds nodes deduplicating by id', function () {
        $node1 = Node::environmentVariable('DATABASE_URL');
        $node2 = Node::environmentVariable('DATABASE_URL');
        $node3 = Node::environmentVariable('APP_SECRET');

        $graph = (new DependencyGraph())
            ->withNode($node1)
            ->withNode($node2)
            ->withNode($node3);

        expect($graph->nodes)->toHaveCount(2);
    });

    it('adds edges deduplicating by hash', function () {
        $edge1 = new Edge('a', 'b', EdgeKind::References, Confidence::Observed);
        $edge2 = new Edge('a', 'b', EdgeKind::References, Confidence::Inferred);
        $edge3 = new Edge('a', 'c', EdgeKind::References, Confidence::Observed);

        $graph = (new DependencyGraph())
            ->withEdge($edge1)
            ->withEdge($edge2)
            ->withEdge($edge3);

        expect($graph->edges)->toHaveCount(2);
    });

    it('merges two graphs without duplicates', function () {
        $node1 = Node::environmentVariable('DATABASE_URL');
        $node2 = Node::configurationKey('doctrine.dbal.url');

        $graph1 = (new DependencyGraph())->withNode($node1);
        $graph2 = (new DependencyGraph())->withNode($node2);
        $merged = $graph1->merge($graph2);

        expect($merged->nodes)->toHaveCount(2);
    });

    it('returns sorted nodes', function () {
        $nodeA = Node::environmentVariable('ZEBRA');
        $nodeB = Node::environmentVariable('ALPHA');

        $graph = (new DependencyGraph())
            ->withNode($nodeA)
            ->withNode($nodeB);

        $sorted = $graph->sortedNodes();
        expect($sorted[0]->id)->toBe('env:ALPHA');
        expect($sorted[1]->id)->toBe('env:ZEBRA');
    });

    it('returns sorted edges', function () {
        $edge1 = new Edge('z', 'a', EdgeKind::References, Confidence::Observed);
        $edge2 = new Edge('a', 'b', EdgeKind::References, Confidence::Observed);

        $graph = (new DependencyGraph())
            ->withEdge($edge1)
            ->withEdge($edge2);

        $sorted = $graph->sortedEdges();
        expect($sorted)->toHaveCount(2);
    });

    it('traverses from a node', function () {
        $nodeA = Node::environmentVariable('DATABASE_URL');
        $nodeB = Node::configurationKey('doctrine.dbal.url');

        $edge = new Edge('env:DATABASE_URL', 'config:doctrine.dbal.url', EdgeKind::MapsTo, Confidence::Observed);

        $graph = (new DependencyGraph())
            ->withNode($nodeA)
            ->withNode($nodeB)
            ->withEdge($edge);

        $visited = $graph->traverseFrom('env:DATABASE_URL');
        expect($visited)->toHaveCount(2);
        expect(array_key_exists('env:DATABASE_URL', $visited))->toBeTrue();
        expect(array_key_exists('config:doctrine.dbal.url', $visited))->toBeTrue();
    });

    it('handles cycles without infinite recursion', function () {
        $nodeA = Node::configurationKey('a');
        $nodeB = Node::configurationKey('b');

        $edge1 = new Edge('config:a', 'config:b', EdgeKind::References, Confidence::Inferred);
        $edge2 = new Edge('config:b', 'config:a', EdgeKind::References, Confidence::Inferred);

        $graph = (new DependencyGraph())
            ->withNode($nodeA)
            ->withNode($nodeB)
            ->withEdge($edge1)
            ->withEdge($edge2);

        $visited = $graph->traverseFrom('config:a');
        expect($visited)->toHaveCount(2);
    });

    it('produces deterministic json', function () {
        $node = Node::environmentVariable('DATABASE_URL', new SourceLocation('.env', 1));
        $graph = (new DependencyGraph())->withNode($node);

        $json1 = $graph->toJson();
        $json2 = $graph->toJson();

        expect($json1)->toBe($json2);
    });

    it('counts nodes by kind', function () {
        $envNode = Node::environmentVariable('DATABASE_URL');
        $configNode = Node::configurationKey('doctrine.dbal.url');
        $phpNode = Node::phpReference('APP_SECRET');

        $graph = (new DependencyGraph())
            ->withNode($envNode)
            ->withNode($configNode)
            ->withNode($phpNode);

        $counts = $graph->nodeCountsByKind();
        expect($counts['environment_variable'])->toBe(1);
        expect($counts['configuration_key'])->toBe(1);
        expect($counts['php_reference'])->toBe(1);
    });

    it('gets outgoing edges', function () {
        $edge1 = new Edge('env:A', 'config:B', EdgeKind::MapsTo, Confidence::Observed);
        $edge2 = new Edge('env:A', 'config:C', EdgeKind::MapsTo, Confidence::Observed);
        $edge3 = new Edge('env:D', 'config:B', EdgeKind::MapsTo, Confidence::Observed);

        $graph = (new DependencyGraph())
            ->withEdge($edge1)
            ->withEdge($edge2)
            ->withEdge($edge3);

        $outgoing = $graph->outgoingEdges('env:A');
        expect($outgoing)->toHaveCount(2);
    });

    it('gets incoming edges', function () {
        $edge1 = new Edge('env:A', 'config:B', EdgeKind::MapsTo, Confidence::Observed);
        $edge2 = new Edge('env:C', 'config:B', EdgeKind::MapsTo, Confidence::Observed);
        $edge3 = new Edge('env:D', 'config:E', EdgeKind::MapsTo, Confidence::Observed);

        $graph = (new DependencyGraph())
            ->withEdge($edge1)
            ->withEdge($edge2)
            ->withEdge($edge3);

        $incoming = $graph->incomingEdges('config:B');
        expect($incoming)->toHaveCount(2);
    });

    it('finds node by id', function () {
        $node = Node::environmentVariable('DATABASE_URL');
        $graph = (new DependencyGraph())->withNode($node);

        $found = $graph->getNode('env:DATABASE_URL');
        expect($found)->not()->toBeNull();
        expect($found->name)->toBe('DATABASE_URL');

        expect($graph->getNode('env:MISSING'))->toBeNull();
    });
});
