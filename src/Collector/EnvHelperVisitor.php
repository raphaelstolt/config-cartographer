<?php

declare(strict_types=1);

namespace ConfigCartographer\Collector;

use PhpParser\Node as PhpNode;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects env() helper calls in Laravel config files.
 *
 * Tracks the current array key path as it traverses nested arrays to build
 * configuration keys like "database.connections.mysql.host".
 */
final class EnvHelperVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, array{line: int, name: string, configKey: string}>
     */
    private array $references = [];

    /**
     * Stack of array keys representing the current nesting path.
     * @var array<int, string>
     */
    private array $keyPath = [];

    public function __construct(
        private readonly string $configPrefix
    ) {
    }

    /**
     * @return array<int, array{line: int, name: string, configKey: string}>
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    public function enterNode(PhpNode $node): ?int
    {
        // Detect env('NAME') or env('NAME', 'default') calls
        if ($node instanceof PhpNode\Expr\FuncCall) {
            if ($node->name instanceof PhpNode\Name) {
                $funcName = $node->name->toString();
                if ($funcName === 'env' && isset($node->args[0])) {
                    $arg = $node->args[0]->value;
                    if ($arg instanceof PhpNode\Scalar\String_) {
                        $envVarName = $arg->value;
                        $configKey = $this->buildConfigKey();

                        $this->references[] = [
                            'line' => $node->getLine(),
                            'name' => $envVarName,
                            'configKey' => $configKey,
                        ];
                    }
                }
            }
        }

        // Track array key paths for nested config structures
        // e.g., 'connections' => ['mysql' => ['host' => env('DB_HOST')]]
        if ($node instanceof PhpNode\Expr\ArrayItem) {
            // Get the key of this array item
            $key = $this->extractArrayKey($node);
            if ($key !== null) {
                $this->keyPath[] = $key;
            }
        }

        return null;
    }

    public function leaveNode(PhpNode $node): ?int
    {
        // Pop the key path when leaving an array item
        if ($node instanceof PhpNode\Expr\ArrayItem) {
            if ($this->keyPath !== []) {
                array_pop($this->keyPath);
            }
        }

        return null;
    }

    /**
     * Build the full config key from the prefix and current key path.
     * e.g., prefix "database" + path ["connections", "mysql", "host"] -> "database.connections.mysql.host"
     */
    private function buildConfigKey(): string
    {
        if ($this->keyPath === []) {
            return $this->configPrefix;
        }

        return $this->configPrefix . '.' . implode('.', $this->keyPath);
    }

    /**
     * Extract the string key from an array item, if it has one.
     */
    private function extractArrayKey(PhpNode\Expr\ArrayItem $node): ?string
    {
        if ($node->key === null) {
            return null;
        }

        if ($node->key instanceof PhpNode\Scalar\String_) {
            return $node->key->value;
        }

        if ($node->key instanceof PhpNode\Expr\ConstFetch) {
            $constName = $node->key->name->toString();
            // Handle common constants like true, false, null as string representations
            return match ($constName) {
                'true' => 'true',
                'false' => 'false',
                'null' => 'null',
                default => null,
            };
        }

        return null;
    }
}
