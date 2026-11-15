<?php

declare(strict_types=1);

namespace ConfigCartographer\Collector;

use PhpParser\Node as PhpNode;
use PhpParser\NodeVisitorAbstract;

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
