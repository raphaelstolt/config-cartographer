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
 * Discovers direct PHP environment access patterns using AST analysis.
 * Detects getenv(), $_ENV[], and $_SERVER[] references.
 */
final class PhpEnvironmentCollector implements CollectorInterface
{
    private const EXCLUDED_DIRS = ['vendor', 'var', 'cache', 'tmp', '.git', 'node_modules'];

    public function collect(string $projectRoot): AnalysisResult
    {
        $graph = new DependencyGraph();
        $diagnostics = [];

        $files = $this->findPhpFiles($projectRoot);

        foreach ($files as $file) {
            try {
                $relativePath = $this->relativePath($file, $projectRoot);
                [$newGraph, $fileDiagnostics] = $this->analyzeFile($file, $relativePath, $graph);
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
    private function findPhpFiles(string $projectRoot): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($projectRoot)
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
    private function analyzeFile(string $filePath, string $relativePath, DependencyGraph $graph): array
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

        if ($ast === null) {
            return [$graph, $diagnostics];
        }

        $visitor = new EnvironmentAccessVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($visitor->getReferences() as $reference) {
            $sourceLocation = new SourceLocation($relativePath, $reference['line']);

            $envVarName = $reference['name'];
            $node = Node::phpReference($envVarName, $sourceLocation);
            $graph = $graph->withNode($node);

            // Also create the environment variable node for linking
            $envNode = Node::environmentVariable($envVarName);
            $graph = $graph->withNode($envNode);

            $confidence = $reference['resolved'] ? Confidence::Observed : Confidence::Unresolved;

            $edge = new Edge(
                $node->id,
                $envNode->id,
                EdgeKind::References,
                $confidence,
                $sourceLocation,
                "{$reference['type']} access to {$envVarName}"
            );
            $graph = $graph->withEdge($edge);
        }

        return [$graph, $diagnostics];
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

/**
 * AST visitor that collects environment variable access patterns.
 */
final class EnvironmentAccessVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, array{line: int, name: string, type: string, resolved: bool}>
     */
    private array $references = [];

    /**
     * @return array<int, array{line: int, name: string, type: string, resolved: bool}>
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    public function enterNode(PhpNode $node): ?int
    {
        // Detect getenv('NAME')
        if ($node instanceof PhpNode\Expr\FuncCall) {
            if ($node->name instanceof PhpNode\Name) {
                $funcName = $node->name->toString();
                if ($funcName === 'getenv' && isset($node->args[0])) {
                    $arg = $node->args[0]->value;
                    if ($arg instanceof PhpNode\Scalar\String_) {
                        $this->references[] = [
                            'line' => $node->getLine(),
                            'name' => $arg->value,
                            'type' => 'getenv()',
                            'resolved' => true,
                        ];
                    } elseif ($arg instanceof PhpNode\Expr\Variable) {
                        // Dynamic: getenv($name) — unresolved
                        $varName = $arg->name instanceof PhpNode\Expr
                            ? 'dynamic'
                            : (string) $arg->name;
                        $this->references[] = [
                            'line' => $node->getLine(),
                            'name' => $varName,
                            'type' => 'getenv()',
                            'resolved' => false,
                        ];
                    }
                }
            }
        }

        // Detect $_ENV['NAME'] and $_SERVER['NAME']
        if ($node instanceof PhpNode\Expr\ArrayDimFetch) {
            if ($node->var instanceof PhpNode\Expr\Variable) {
                $varName = $node->var->name;
                // In php-parser 5.x, name can be string or Identifier
                if (is_object($varName) && method_exists($varName, 'toString')) {
                    $varName = $varName->toString();
                } else {
                    $varName = (string) $varName;
                }

                if ($varName === '_ENV' || $varName === '_SERVER') {
                    if ($node->dim instanceof PhpNode\Scalar\String_) {
                        $this->references[] = [
                            'line' => $node->getLine(),
                            'name' => $node->dim->value,
                            'type' => "\${$varName}[]",
                            'resolved' => true,
                        ];
                    } elseif ($node->dim instanceof PhpNode\Expr\Variable) {
                        $dimName = $node->dim->name;
                        if (is_object($dimName) && method_exists($dimName, 'toString')) {
                            $dimName = $dimName->toString();
                        } elseif ($dimName instanceof PhpNode\Expr) {
                            $dimName = 'dynamic';
                        } else {
                            $dimName = (string) $dimName;
                        }
                        $this->references[] = [
                            'line' => $node->getLine(),
                            'name' => $dimName,
                            'type' => "\${$varName}[]",
                            'resolved' => false,
                        ];
                    }
                }
            }
        }

        return null;
    }
}
