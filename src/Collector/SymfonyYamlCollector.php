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
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Discovers %env(...)% references in Symfony YAML configuration files.
 */
final class SymfonyYamlCollector implements CollectorInterface
{
    private const EXCLUDED_DIRS = ['vendor', 'var', 'cache', 'tmp', '.git'];

    public function collect(string $projectRoot): AnalysisResult
    {
        $graph = new DependencyGraph();
        $diagnostics = [];

        $files = $this->findYamlFiles($projectRoot);

        foreach ($files as $file) {
            try {
                $relativePath = $this->relativePath($file, $projectRoot);
                $content = file_get_contents($file);
                if ($content === false) {
                    $diagnostics[] = new Diagnostic(
                        "Could not read file: {$relativePath}",
                        DiagnosticSeverity::Error,
                        new SourceLocation($relativePath)
                    );
                    continue;
                }

                // Parse YAML for structure
                $parsed = Yaml::parse($content);

                // Extract env references from the raw content for line numbers
                [$newGraph, $fileDiagnostics] = $this->extractReferences(
                    $content,
                    $relativePath,
                    $parsed,
                    $graph
                );
                $graph = $newGraph;
                $diagnostics = [...$diagnostics, ...$fileDiagnostics];
            } catch (ParseException $e) {
                $relativePath = $this->relativePath($file, $projectRoot);
                $diagnostics[] = new Diagnostic(
                    "Invalid YAML in {$relativePath}: {$e->getMessage()}",
                    DiagnosticSeverity::Error,
                    new SourceLocation($relativePath)
                );
            } catch (\Throwable $e) {
                $relativePath = $this->relativePath($file, $projectRoot);
                $diagnostics[] = new Diagnostic(
                    "Error processing {$relativePath}: {$e->getMessage()}",
                    DiagnosticSeverity::Error,
                    new SourceLocation($relativePath)
                );
            }
        }

        return new AnalysisResult($graph, $diagnostics);
    }

    /**
     * @return string[] List of absolute file paths
     */
    private function findYamlFiles(string $projectRoot): array
    {
        $configDir = $projectRoot . '/config';
        if (!is_dir($configDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($configDir)
            ->name('/\.ya?ml$/')
            ->ignoreDotFiles(false)
            ->ignoreUnreadableDirs(true);

        // Exclude common directories
        foreach (self::EXCLUDED_DIRS as $dir) {
            $finder->notPath('#' . preg_quote($dir, '#') . '#');
        }

        $files = [];
        foreach ($finder as $file) {
            $path = $file->getPathname();

            // Symlink safety
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
    private function extractReferences(
        string $content,
        string $relativePath,
        mixed $parsed,
        DependencyGraph $graph
    ): array {
        $diagnostics = [];
        $lines = explode("\n", $content);

        // Build a map of config paths from parsed YAML
        $configPaths = $this->extractConfigPaths($parsed, '');

        // Find all %env(...)% patterns in the raw content
        $envPattern = '/%env\(([^)]+)\)%/';
        preg_match_all($envPattern, $content, $allMatches, PREG_OFFSET_CAPTURE);

        foreach ($allMatches[1] as $match) {
            $envExpression = $match[0];
            $offset = $match[1];

            // Calculate line number from offset
            $lineNumber = $this->offsetToLine($content, $offset);

            // Extract the env var name from the expression
            $envVarName = $this->extractEnvVarName($envExpression);
            if ($envVarName === null) {
                continue;
            }

            $sourceLocation = new SourceLocation($relativePath, $lineNumber);

            // Create or find the env variable node
            $envNode = Node::environmentVariable($envVarName, $sourceLocation);
            $graph = $graph->withNode($envNode);

            // Find which config key this reference belongs to
            $configPath = $this->findConfigPathForLine($configPaths, $lineNumber, $lines);

            if ($configPath !== null) {
                $configNode = Node::configurationKey($configPath, $sourceLocation);
                $graph = $graph->withNode($configNode);

                $edge = new Edge(
                    $envNode->id,
                    $configNode->id,
                    EdgeKind::MapsTo,
                    Confidence::Observed,
                    $sourceLocation,
                    "%env({$envExpression})% maps to {$configPath}"
                );
                $graph = $graph->withEdge($edge);
            }
        }

        return [$graph, $diagnostics];
    }

    /**
     * Extract the environment variable name from an env() expression.
     * Handles processors like resolve:, default:, not: etc.
     */
    private function extractEnvVarName(string $expression): ?string
    {
        // Handle processor syntax: env(processor:NAME)
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*:([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $matches)) {
            return $matches[1];
        }

        // Plain env variable name: env(NAME)
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression)) {
            return $expression;
        }

        return null;
    }

    /**
     * Extract configuration paths from parsed YAML structure.
     * Returns an array of path => line_number mappings.
     */
    private function extractConfigPaths(mixed $data, string $prefix): array
    {
        $paths = [];

        if (!is_array($data)) {
            return $paths;
        }

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $paths = [...$paths, ...$this->extractConfigPaths($value, $path)];
            } else {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    /**
     * Find the config path that contains a reference on a given line.
     */
    private function findConfigPathForLine(array $configPaths, int $lineNumber, array $lines): ?string
    {
        // Simple heuristic: find the nearest config key path above the line
        $lineContent = $lines[$lineNumber - 1] ?? '';

        // Try to extract the config path from the YAML structure context
        // This is approximate since we can't perfectly map YAML lines to paths
        $path = null;
        $indent = strlen($lineContent) - strlen(ltrim($lineContent));

        // Walk backwards to find the most recent key at a lower or equal indent
        for ($i = $lineNumber - 2; $i >= 0; $i--) {
            $prevLine = $lines[$i];
            $prevTrimmed = trim($prevLine);

            if ($prevTrimmed === '' || str_starts_with($prevTrimmed, '#')) {
                continue;
            }

            $prevIndent = strlen($prevLine) - strlen(ltrim($prevLine));
            if ($prevIndent <= $indent && preg_match('/^([a-zA-Z_][a-zA-Z0-9_.]*)\s*:/', $prevTrimmed, $m)) {
                $path = $m[1];
                break;
            }
        }

        return $path;
    }

    private function offsetToLine(string $content, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }
        return substr_count($content, "\n", 0, $offset) + 1;
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
