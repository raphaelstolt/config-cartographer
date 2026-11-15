<?php

declare(strict_types=1);

namespace ConfigCartographer\Command;

use ConfigCartographer\Collector\DotenvCollector;
use ConfigCartographer\Collector\LaravelConfigCollector;
use ConfigCartographer\Collector\PhpEnvironmentCollector;
use ConfigCartographer\Collector\SymfonyYamlCollector;
use ConfigCartographer\Domain\CollectorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'graph', description: 'Generate a visual graph of configuration dependencies')]
class GraphCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to the project root', '.')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (mermaid or json)', 'mermaid')
            ->addOption('variable', null, InputOption::VALUE_REQUIRED, 'Focus on a specific variable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->resolvePath($input->getOption('path'));

        if (!$this->isValidPath($path)) {
            $output->writeln('<error>Invalid project path: ' . escapeshellarg($path) . '</error>');
            return Command::FAILURE;
        }

        $format = $input->getOption('format');
        if (!in_array($format, ['mermaid', 'json'], true)) {
            $output->writeln('<error>Invalid format: ' . escapeshellarg($format) . '. Use "mermaid" or "json".</error>');
            return Command::FAILURE;
        }

        $collectors = $this->getCollectors();
        $result = $this->runCollectors($collectors, $path);

        $variable = $input->getOption('variable');
        $graph = $result->graph;

        // If focusing on a specific variable, filter the graph
        if ($variable !== null) {
            $envNodeId = 'env:' . $variable;
            $node = $graph->getNode($envNodeId);
            if ($node === null) {
                $output->writeln('<error>Variable not found: ' . escapeshellarg($variable) . '</error>');
                return Command::FAILURE;
            }
            $visitedIds = array_keys($graph->traverseFrom($envNodeId));
            $graph = $this->filterGraph($graph, $visitedIds);
        }

        if ($format === 'json') {
            $output->writeln($graph->toJson());
        } else {
            $output->writeln($this->toMermaid($graph));
        }

        return Command::SUCCESS;
    }

    /**
     * @return CollectorInterface[]
     */
    private function getCollectors(): array
    {
        return [
            new DotenvCollector(),
            new SymfonyYamlCollector(),
            new LaravelConfigCollector(),
            new PhpEnvironmentCollector(),
        ];
    }

    private function runCollectors(array $collectors, string $path): \ConfigCartographer\Domain\AnalysisResult
    {
        $result = new \ConfigCartographer\Domain\AnalysisResult(
            new \ConfigCartographer\Domain\DependencyGraph()
        );

        foreach ($collectors as $collector) {
            $result = $result->merge($collector->collect($path));
        }

        return $result;
    }

    private function filterGraph(\ConfigCartographer\Domain\DependencyGraph $graph, array $nodeIds): \ConfigCartographer\Domain\DependencyGraph
    {
        $newGraph = new \ConfigCartographer\Domain\DependencyGraph();
        $idSet = array_flip($nodeIds);

        foreach ($graph->nodes as $id => $node) {
            if (isset($idSet[$id])) {
                $newGraph = $newGraph->withNode($node);
            }
        }

        foreach ($graph->edges as $edge) {
            if (isset($idSet[$edge->sourceId], $idSet[$edge->targetId])) {
                $newGraph = $newGraph->withEdge($edge);
            }
        }

        return $newGraph;
    }

    private function toMermaid(\ConfigCartographer\Domain\DependencyGraph $graph): string
    {
        $lines = ['flowchart TD'];

        $nodeShapes = [
            'environment_variable' => '([%s])',
            'configuration_key' => '[%s]',
            'php_reference' => '(%s)',
        ];

        $usedIds = [];
        foreach ($graph->sortedNodes() as $node) {
            $safeId = $this->safeMermaidId($node->id);
            $usedIds[$node->id] = $safeId;

            $shapeTemplate = $nodeShapes[$node->kind->value];
            $label = $this->escapeMermaidLabel($node->name);
            $shape = sprintf($shapeTemplate, $label);
            $lines[] = "    {$safeId}{$shape}";
        }

        foreach ($graph->sortedEdges() as $edge) {
            $sourceId = $usedIds[$edge->sourceId] ?? null;
            $targetId = $usedIds[$edge->targetId] ?? null;
            if ($sourceId === null || $targetId === null) {
                continue;
            }

            $arrow = match ($edge->kind->value) {
                'declared_in' => '-->',
                'references' => '.->',
                'maps_to' => '==>',
            };

            $label = $edge->confidence->value;
            $lines[] = "    {$sourceId} {$arrow}|{$label}| {$targetId}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function safeMermaidId(string $id): string
    {
        // Replace special characters with underscores for valid Mermaid IDs
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $id);
    }

    private function escapeMermaidLabel(string $label): string
    {
        // Escape special characters in Mermaid labels
        return str_replace(['"', '\\'], ['\\"', '\\\\'], $label);
    }

    private function resolvePath(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            return $path;
        }
        return $resolved;
    }

    private function isValidPath(string $path): bool
    {
        return is_dir($path) && is_readable($path);
    }
}
