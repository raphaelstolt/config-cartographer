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
use PhpParser\Node as PhpNode;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * Discovers env() helper calls in Laravel PHP configuration files.
 *
 * Laravel config files are PHP files in the `config/` directory that return
 * associative arrays. The `env()` helper is used to read environment variables,
 * typically with a default value: `env('DB_HOST', '127.0.0.1')`.
 *
 * This collector maps each `env()` call to a configuration key path like
 * `database.connections.mysql.host` based on the file name and array key path.
 */
final class LaravelConfigCollector implements CollectorInterface
{
    private const EXCLUDED_DIRS = ['vendor', 'storage', 'bootstrap/cache', 'node_modules', '.git'];

    public function collect(string $projectRoot): AnalysisResult
    {
        $graph = new DependencyGraph();
        $diagnostics = [];

        $files = $this->findConfigFiles($projectRoot);

        foreach ($files as $file) {
            try {
                $relativePath = $this->relativePath($file, $projectRoot);
                [$newGraph, $fileDiagnostics] = $this->analyzeFile($file, $relativePath, $projectRoot, $graph);
                $graph = $newGraph;
                $diagnostics = [...$diagnostics, ...$fileDiagnostics];
            } catch (\Throwable $e) {
                $relativePath = $this->relativePath($file, $projectRoot);
                $diagnostics[] = new Diagnostic(
                    "Failed to parse {$relativePath}: {$e->getMessage()}",
                    DiagnosticSeverity::Warning,
                    new SourceLocation($relativePath)
                );
            }
        }

        return new AnalysisResult($graph, $diagnostics);
    }

    /**
     * @return string[] List of absolute file paths
     */
    private function findConfigFiles(string $projectRoot): array
    {
        $configDir = $projectRoot . '/config';
        if (!is_dir($configDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($configDir)
            ->name('*.php')
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
    private function analyzeFile(string $filePath, string $relativePath, string $projectRoot, DependencyGraph $graph): array
    {
        $diagnostics = [];
        $content = file_get_contents($filePath);
        if ($content === false) {
            $diagnostics[] = new Diagnostic(
                "Could not read file: {$relativePath}",
                DiagnosticSeverity::Error,
                new SourceLocation($relativePath)
            );
            return [$graph, $diagnostics];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($content);

        if ($ast === null || $ast === []) {
            return [$graph, $diagnostics];
        }

        // Derive the config file prefix from the filename (e.g., "database" from "config/database.php")
        $configPrefix = $this->getConfigPrefix($relativePath);

        $visitor = new EnvHelperVisitor($configPrefix);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($visitor->getReferences() as $reference) {
            $sourceLocation = new SourceLocation($relativePath, $reference['line']);

            $envVarName = $reference['name'];
            $configKey = $reference['configKey'];

            // Create the environment variable node
            $envNode = Node::environmentVariable($envVarName);
            $graph = $graph->withNode($envNode);

            // Create the configuration key node
            $configNode = Node::configurationKey($configKey, $sourceLocation);
            $graph = $graph->withNode($configNode);

            // Link the config key to the environment variable it references
            $edge = new Edge(
                $configNode->id,
                $envNode->id,
                EdgeKind::References,
                Confidence::Observed,
                $sourceLocation,
                "Laravel env() reference to {$envVarName}"
            );
            $graph = $graph->withEdge($edge);
        }

        return [$graph, $diagnostics];
    }

    /**
     * Extract the config file prefix from the relative path.
     * e.g., "config/database.php" -> "database"
     * e.g., "config/database/mysql.php" -> "database.mysql"
     */
    private function getConfigPrefix(string $relativePath): string
    {
        // Remove the config/ prefix and .php extension
        $path = $relativePath;
        if (str_starts_with($path, 'config/')) {
            $path = substr($path, 7);
        } elseif (str_starts_with($path, 'config\\')) {
            $path = substr($path, 7);
        }

        // Remove .php extension
        $path = preg_replace('/\.php$/', '', $path);

        // Convert path separators to dots for nested config files
        return str_replace(['/', '\\'], '.', $path);
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
