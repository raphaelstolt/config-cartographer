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

#[AsCommand(name: 'scan', description: 'Scan a project for configuration declarations and references')]
class ScanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to the project root', '.')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (console or json)', 'console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

        if ($format === 'json') {
            $output->writeln($this->toJson($result));
        } else {
            $this->outputConsole($output, $result);
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

    private function outputConsole(OutputInterface $output, \ConfigCartographer\Domain\AnalysisResult $result): void
    {
        $counts = $result->graph->nodeCountsByKind();

        $output->writeln('');
        $output->writeln('<fg=blue;options=bold>Configuration Scan Results</>');
        $output->writeln('');
        $output->writeln('  <fg=green>Environment variables:</> ' . $counts['environment_variable']);
        $output->writeln('  <fg=yellow>Configuration keys:</>   ' . $counts['configuration_key']);
        $output->writeln('  <fg=magenta>PHP references:</>       ' . $counts['php_reference']);
        $output->writeln('  <fg=cyan>Total edges:</>            ' . count($result->graph->edges));
        $output->writeln('');

        if ($result->diagnostics !== []) {
            $output->writeln('<fg=yellow;options=bold>Diagnostics:</>');
            foreach ($result->diagnostics as $diagnostic) {
                $prefix = match ($diagnostic->severity->value) {
                    'error' => '<fg=red>[ERROR]</>',
                    'warning' => '<fg=yellow>[WARNING]</>',
                    default => '[INFO]',
                };
                $output->writeln("  {$prefix} {$diagnostic->message}");
            }
            $output->writeln('');
        }
    }

    private function toJson(\ConfigCartographer\Domain\AnalysisResult $result): string
    {
        $data = [
            'version' => '0.1',
            'summary' => [
                'environment_variables' => $result->graph->nodeCountsByKind()['environment_variable'],
                'configuration_keys' => $result->graph->nodeCountsByKind()['configuration_key'],
                'php_references' => $result->graph->nodeCountsByKind()['php_reference'],
                'edges' => count($result->graph->edges),
                'diagnostics' => count($result->diagnostics),
            ],
            'graph' => json_decode($result->graph->toJson(), true),
            'diagnostics' => array_map(
                static fn ($d) => [
                    'message' => $d->message,
                    'severity' => $d->severity->value,
                    'source_location' => $d->sourceLocation !== null
                        ? ['file' => $d->sourceLocation->file, 'line' => $d->sourceLocation->line]
                        : null,
                ],
                $result->diagnostics
            ),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
