<?php

declare(strict_types=1);

use ConfigCartographer\Domain\AnalysisResult;
use ConfigCartographer\Domain\DependencyGraph;
use ConfigCartographer\Domain\Diagnostic;
use ConfigCartographer\Domain\DiagnosticSeverity;
use ConfigCartographer\Domain\Node;

describe(AnalysisResult::class, function () {
    it('starts with empty diagnostics', function () {
        $graph = new DependencyGraph();
        $result = new AnalysisResult($graph);
        expect($result->diagnostics)->toBe([]);
    });

    it('adds diagnostics immutably', function () {
        $graph = new DependencyGraph();
        $result = new AnalysisResult($graph);
        $diag = new Diagnostic('File not found', DiagnosticSeverity::Warning);
        $newResult = $result->withDiagnostic($diag);

        expect($result->diagnostics)->toBe([]);
        expect($newResult->diagnostics)->toHaveCount(1);
    });

    it('merges two results', function () {
        $node = Node::environmentVariable('DATABASE_URL');
        $graph1 = (new DependencyGraph())->withNode($node);
        $result1 = new AnalysisResult($graph1);

        $node2 = Node::configurationKey('doctrine.dbal.url');
        $graph2 = (new DependencyGraph())->withNode($node2);
        $diag = new Diagnostic('Parse warning', DiagnosticSeverity::Warning);
        $result2 = new AnalysisResult($graph2, [$diag]);

        $merged = $result1->merge($result2);
        expect($merged->nodeCount())->toBe(2);
        expect($merged->diagnostics)->toHaveCount(1);
    });

    it('groups diagnostics by severity', function () {
        $diag1 = new Diagnostic('Error message', DiagnosticSeverity::Error);
        $diag2 = new Diagnostic('Warning message', DiagnosticSeverity::Warning);
        $diag3 = new Diagnostic('Another error', DiagnosticSeverity::Error);

        $graph = new DependencyGraph();
        $result = new AnalysisResult($graph, [$diag1, $diag2, $diag3]);

        $grouped = $result->diagnosticsBySeverity();
        expect($grouped['error'])->toHaveCount(2);
        expect($grouped['warning'])->toHaveCount(1);
    });

    it('detects errors', function () {
        $graph = new DependencyGraph();
        $result = new AnalysisResult($graph);
        expect($result->hasErrors())->toBeFalse();

        $diag = new Diagnostic('Error', DiagnosticSeverity::Error);
        $resultWithError = new AnalysisResult($graph, [$diag]);
        expect($resultWithError->hasErrors())->toBeTrue();
    });

    it('counts nodes and edges', function () {
        $node = Node::environmentVariable('DATABASE_URL');
        $graph = (new DependencyGraph())->withNode($node);
        $result = new AnalysisResult($graph);

        expect($result->nodeCount())->toBe(1);
        expect($result->edgeCount())->toBe(0);
    });
});
