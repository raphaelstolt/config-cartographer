<?php

declare(strict_types=1);

use ConfigCartographer\Domain\Confidence;
use ConfigCartographer\Domain\Edge;
use ConfigCartographer\Domain\EdgeKind;
use ConfigCartographer\Domain\SourceLocation;

describe(Edge::class, function () {
    it('creates an edge with evidence', function () {
        $evidence = new SourceLocation('config/packages/doctrine.yaml', 12);
        $edge = new Edge(
            'env:DATABASE_URL',
            'config:doctrine.dbal.url',
            EdgeKind::MapsTo,
            Confidence::Observed,
            $evidence
        );

        expect($edge->sourceId)->toBe('env:DATABASE_URL');
        expect($edge->targetId)->toBe('config:doctrine.dbal.url');
        expect($edge->kind)->toBe(EdgeKind::MapsTo);
        expect($edge->confidence)->toBe(Confidence::Observed);
        expect($edge->evidence)->toBe($evidence);
    });

    it('provides equality based on source, target, and kind', function () {
        $edge1 = new Edge('a', 'b', EdgeKind::References, Confidence::Observed);
        $edge2 = new Edge('a', 'b', EdgeKind::References, Confidence::Inferred);
        $edge3 = new Edge('a', 'b', EdgeKind::MapsTo, Confidence::Observed);

        expect($edge1->equals($edge2))->toBeTrue();
        expect($edge1->equals($edge3))->toBeFalse();
    });

    it('generates deterministic hash', function () {
        $edge1 = new Edge('a', 'b', EdgeKind::References, Confidence::Observed);
        $edge2 = new Edge('a', 'b', EdgeKind::References, Confidence::Inferred);

        expect($edge1->hash())->toBe($edge2->hash());
    });

    it('creates an edge without evidence', function () {
        $edge = new Edge('a', 'b', EdgeKind::References, Confidence::Unresolved);
        expect($edge->evidence)->toBeNull();
        expect($edge->description)->toBeNull();
    });
});
