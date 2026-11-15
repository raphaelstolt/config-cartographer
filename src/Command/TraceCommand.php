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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'trace', description: 'Trace all references for a specific configuration key')]
class TraceCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('variable', InputArgument::REQUIRED, 'The configuration key or environment variable name to trace')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to the project root', '.')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (console or json)', 'console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $variable = $input->getArgument('variable');
        $path = $this->resolvePath($input->getOption('path'));

        if (!$this->isValidPath($path)) {
            $output->writeln('<error>Invalid project path: ' . escapeshellarg($path) . '</error>');
            return Command::FAILURE;
        }

        $format = $input->getOption('format');
        if (!in_array($format, ['console', 'json'], true)) {
            $output->writeln('<error>Invalid format: ' . escapeshellarg($format) . '. Use "console" or "json".</error>');
            return Command::FAILURE;
        }

        $collectors = $this->getCollectors();
        $result = $this->runCollectors($collectors, $path);

        // Try to find the node by env: prefix first, then config: prefix
        $envNodeId = 'env:' . $variable;
        $configNodeId = 'config:' . $variable;

        $node = $result->graph->getNode($envNodeId);
        if ($node === null) {
            $node = $result->graph->getNode($configNodeId);
        }

        if ($node === null) {
            $output->writeln('<error>Variable not found: ' . escapeshellarg($variable) . '</error>');
            $output->writeln('');
            $output->writeln('No declarations or references found for <info>' . $variable . '</info>.');
            $output->writeln('The variable may not be declared in any .env file, YAML config, or PHP source.');
            return Command::FAILURE;
        }

        $traceData = $this->buildTrace($result, $node);

        if ($format === 'json') {
            $output->writeln($this->toJson($traceData));
        } else {
            $this->outputConsole($output, $traceData);
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

    private function buildTrace(\ConfigCartographer\Domain\AnalysisResult $result, \ConfigCartographer\Domain\Node $node): array
    {
        $visited = $result->graph->traverseFrom($node->id);

        $declarations = [];
        $references = [];
        $configKeys = [];

        foreach ($result->graph->outgoingEdges($node->id) as $edge) {
            if ($edge->kind->value === 'declared_in') {
                $declarations[] = [
                    'file' => $edge->evidence->file ?? 'unknown',
                    'line' => $edge->evidence->line,
                    'confidence' => $edge->confidence->value,
                ];
            } elseif ($edge->kind->value === 'maps_to') {
                $targetNode = $result->graph->getNode($edge->targetId);
                $configKeys[] = [
                    'key' => $targetNode->name ?? $edge->targetId,
                    'file' => $edge->evidence->file ?? 'unknown',
                    'line' => $edge->evidence->line,
                    'confidence' => $edge->confidence->value,
                ];
            } elseif ($edge->kind->value === 'references') {
                $references[] = [
                    'file' => $edge->evidence->file ?? 'unknown',
                    'line' => $edge->evidence->line,
                    'confidence' => $edge->confidence->value,
                    'description' => $edge->description,
                ];
            }
        }

        // Also check incoming edges for references to this node
        foreach ($result->graph->incomingEdges($node->id) as $edge) {
            $sourceNode = $result->graph->getNode($edge->sourceId);
            if ($sourceNode?->kind->value === 'php_reference') {
                $references[] = [
                    'file' => $edge->evidence->file ?? 'unknown',
                    'line' => $edge->evidence->line,
                    'confidence' => $edge->confidence->value,
                    'description' => $edge->description,
                ];
            }
        }

        return [
            'variable' => $node->name,
            'kind' => $node->kind->value,
            'declarations' => $declarations,
            'references' => $references,
            'config_keys' => $configKeys,
            'diagnostics' => array_map(
                static fn ($d) => ['message' => $d->message, 'severity' => $d->severity->value],
                $result->diagnostics
            ),
        ];
    }

    private function outputConsole(OutputInterface $output, array $trace): void
    {
        $output->writeln('');
        $output->writeln('<fg=blue;options=bold>Trace: ' . $trace['variable'] . '</>');
        $output->writeln('<fg=gray>Kind: ' . $trace['kind'] . '</>');
        $output->writeln('');

        if ($trace['declarations'] !== []) {
            $output->writeln('<fg=green;options=bold>Declarations:</>');
            foreach ($trace['declarations'] as $decl) {
                $loc = $decl['file'] . ($decl['line'] !== null ? ':' . $decl['line'] : '');
                $output->writeln("  • {$loc} <fg=gray>({$decl['confidence']})</>");
            }
            $output->writeln('');
        }

        if ($trace['config_keys'] !== []) {
            $output->writeln('<fg=yellow;options=bold>Configuration keys:</>');
            foreach ($trace['config_keys'] as $key) {
                $loc = $key['file'] . ($key['line'] !== null ? ':' . $key['line'] : '');
                $output->writeln("  • {$key['key']} at {$loc} <fg=gray>({$key['confidence']})</>");
            }
            $output->writeln('');
        }

        if ($trace['references'] !== []) {
            $output->writeln('<fg=magenta;options=bold>PHP references:</>');
            foreach ($trace['references'] as $ref) {
                $loc = $ref['file'] . ($ref['line'] !== null ? ':' . $ref['line'] : '');
                $desc = $ref['description'] ? " ({$ref['description']})" : '';
                $output->writeln("  • {$loc}{$desc} <fg=gray>({$ref['confidence']})</>");
            }
            $output->writeln('');
        }

        if ($trace['declarations'] === [] && $trace['config_keys'] === [] && $trace['references'] === []) {
            $output->writeln('<comment>No references found for this variable.</>');
            $output->writeln('');
        }
    }

    private function toJson(array $trace): string
    {
        return json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
