<?php

declare(strict_types=1);

namespace ConfigCartographer\Collector;

use ConfigCartographer\Domain\AnalysisResult;
use ConfigCartographer\Domain\CollectorInterface;
use ConfigCartographer\Domain\Confidence;
use ConfigCartographer\Domain\DependencyGraph;
use ConfigCartographer\Domain\Diagnostic;
use ConfigCartographer\Domain\DiagnosticSeverity;
use ConfigCartographer\Domain\Edge;
use ConfigCartographer\Domain\EdgeKind;
use ConfigCartographer\Domain\Node;
use ConfigCartographer\Domain\SourceLocation;
use Symfony\Component\Finder\Finder;

/**
 * Discovers environment variable declarations in .env files.
 * Collects key names only — never retains or outputs parsed values.
 */
final class DotenvCollector implements CollectorInterface
{
    /**
     * Default .env file patterns to discover.
     */
    private const DEFAULT_PATTERNS = ['.env', '.env.example'];

    /**
     * Directories to exclude by default.
     */
    private const EXCLUDED_DIRS = ['vendor', 'var', 'cache', 'tmp', '.git'];

    public function collect(string $projectRoot): AnalysisResult
    {
        $graph = new DependencyGraph();
        $diagnostics = [];

        $files = $this->findDotenvFiles($projectRoot);

        foreach ($files as $file) {
            try {
                $relativePath = $this->relativePath($file, $projectRoot);
                [$newGraph, $fileDiagnostics] = $this->parseFile($file, $relativePath, $graph);
                $graph = $newGraph;
                $diagnostics = [...$diagnostics, ...$fileDiagnostics];
            } catch (\Throwable $e) {
                $diagnostics[] = new Diagnostic(
                    "Failed to read {$file}: {$e->getMessage()}",
                    DiagnosticSeverity::Error,
                    new SourceLocation($this->relativePath($file, $projectRoot))
                );
            }
        }

        return new AnalysisResult($graph, $diagnostics);
    }

    /**
     * @return string[] List of absolute file paths
     */
    private function findDotenvFiles(string $projectRoot): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($projectRoot)
            ->name('.env*')
            ->ignoreDotFiles(false)
            ->ignoreUnreadableDirs(true);

        // Exclude common directories
        foreach (self::EXCLUDED_DIRS as $dir) {
            $finder->notPath('#' . preg_quote($dir, '#') . '#');
        }

        $files = [];
        foreach ($finder as $file) {
            $path = $file->getPathname();

            // Symlink safety: ensure resolved path stays within project root
            $realPath = realpath($path);
            if ($realPath === false || !str_starts_with($realPath, realpath($projectRoot))) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * @return array{DependencyGraph, Diagnostic[]}
     */
    private function parseFile(string $filePath, string $relativePath, DependencyGraph $graph): array
    {
        $diagnostics = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $diagnostics[] = new Diagnostic(
                "Could not read file: {$relativePath}",
                DiagnosticSeverity::Error,
                new SourceLocation($relativePath)
            );
            return [$graph, $diagnostics];
        }

        $lineNumber = 0;
        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);

            // Skip comments and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $key = $this->extractKey($trimmed, $lineNumber);
            if ($key === null) {
                continue;
            }

            $sourceLocation = new SourceLocation($relativePath, $lineNumber);
            $node = Node::environmentVariable($key, $sourceLocation);
            $graph = $graph->withNode($node);

            $edge = new Edge(
                $node->id,
                $node->id,
                EdgeKind::DeclaredIn,
                Confidence::Observed,
                $sourceLocation,
                "Declared in {$relativePath}"
            );
            $graph = $graph->withEdge($edge);
        }

        return [$graph, $diagnostics];
    }

    /**
     * Extract the variable name from a dotenv line.
     * Returns null if the line is not a valid declaration.
     */
    private function extractKey(string $line, int $lineNumber): ?string
    {
        // Remove export prefix
        $line = ltrim($line);
        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        // Match KEY=... or KEY="..." or KEY='...'
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function relativePath(string $file, string $projectRoot): string
    {
        $root = rtrim($projectRoot, '/\\');
        if (str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            return substr($file, strlen($root) + 1);
        }
        return $file;
    }
}
