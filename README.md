# config-cartographer

Discover, trace, and visualise application configuration dependencies across PHP projects.

## Why?

PHP applications scatter configuration across multiple layers: `.env` files declare variables, framework config files
reference them, and application code reads them through `getenv()`, `$_ENV`, or framework helpers. When a value
changes — or when onboarding to an unfamiliar codebase — it's challenging to answer: _"Where is this variable used,
and what breaks if I change it?"_

`config-cartographer` statically maps these cross-layer relationships with source-level evidence. It never executes
the target application, never exposes secret values, and never claims complete runtime knowledge — only what static
analysis can confidently observe.

## Installation

### Via Composer (dev dependency)

```bash
composer require --dev stolt/config-cartographer
```

## Usage

```
php bin/config-cartographer <COMMAND>
```

### Commands

| Command  | Description                                                        |
|----------|--------------------------------------------------------------------|
| `scan`   | Discover all configuration declarations and references.            |
| `trace`  | Trace all usages of a specific environment variable.               |
| `graph`  | Generate a dependency graph in Mermaid or JSON format.             |

### `scan` — Discover configuration

Finds all environment variable declarations, configuration key references, and direct PHP environment access in a
project.

```bash
# Scan the current directory
php bin/config-cartographer scan

# Scan a specific project
php bin/config-cartographer scan --path=/path/to/project

# Output as JSON
php bin/config-cartographer scan --format=json
```

Example output:

```
Configuration Scan Results

  Environment variables: 5
  Configuration keys:    2
  PHP references:        3
  Total edges:           8
```

### `trace` — Trace a variable

Shows all declarations, configuration keys, and PHP references for a specific environment variable.

```bash
# Trace a variable in the current directory
php bin/config-cartographer trace DATABASE_URL

# Trace in a specific project
php bin/config-cartographer trace DATABASE_URL --path=/path/to/project

# Output as JSON
php bin/config-cartographer trace DATABASE_URL --format=json
```

Example output:

```
Trace: DATABASE_URL
Kind: environment_variable

Declarations:
  • .env:1 (observed)

Configuration keys:
  • dbal at config/packages/doctrine.yaml:3 (observed)

PHP references:
  • src/Database.php:5 (getenv() access to DATABASE_URL) (observed)
```

### `graph` — Generate a dependency graph

Produces a Mermaid flowchart or JSON representation of the configuration dependency graph.

```bash
# Generate a full graph
php bin/config-cartographer graph

# Focus on a single variable
php bin/config-cartographer graph --variable=DB_HOST

# Output as JSON
php bin/config-cartographer graph --format=json
```

## Options and flags

All commands support these common options:

| Option       | Description                                                    |
|--------------|----------------------------------------------------------------|
| `--path`     | Path to project root (default: current directory)              |
| `--format`   | Output format: `console`, `json`, or `mermaid`                 |
| `--variable` | Filter graph output to a single environment variable (`graph`) |

## Supported Reference Patterns

| Pattern | Collector | Confidence |
|---------|-----------|------------|
| `.env` key declarations | DotenvCollector | observed |
| `%env(NAME)%` in YAML | SymfonyYamlCollector | observed |
| `%env(resolve:NAME)%` in YAML | SymfonyYamlCollector | observed |
| `env('NAME')` in Laravel config | LaravelConfigCollector | observed |
| `getenv('NAME')` in PHP | PhpEnvironmentCollector | observed |
| `$_ENV['NAME']` in PHP | PhpEnvironmentCollector | observed |
| `$_SERVER['NAME']` in PHP | PhpEnvironmentCollector | observed |
| `getenv($name)` (dynamic) | PhpEnvironmentCollector | unresolved |

## Confidence Levels

- __observed__: Directly found in source code (e.g., `getenv('DATABASE_URL')`)
- __inferred__: Derived from context (e.g. YAML structure analysis)
- __unresolved__: Dynamic or computed reference where the target cannot be determined statically

## Security Model

- __Never executes__ the target application during analysis
- __Never displays, persists, or exports__ secret values — only variable names, file paths, reference types, and
  source locations
- __Symlink-safe__: refuses to follow symlinks that escape the project root
- __Excludes__ `vendor/`, `var/`, `cache/`, `tmp/`, `.git/` by default

## Limitations

- Does not resolve YAML imports or execute framework code
- Does not analyse the compiled Symfony container
- Not a general-purpose unused-variable detector
- Static analysis cannot claim complete runtime dependency knowledge

## AI disclaimer

This package is developed with the assistance of Artificial Intelligence (AI). AI tools are used throughout the
development process, including code generation, documentation, testing, and maintenance.

While reasonable care is taken to review and validate AI-generated contributions, AI-generated code is not inherently
correct, secure, or free of defects. All contributions remain subject to human review and project quality standards.

As with any software, use this package at your own risk. No guarantee is made regarding its fitness for a particular
purpose, and the maintainers assume no liability for damages resulting from its use.

## Contributing

If you're considering contributing to this project, have a look at this repository's
[CONTRIBUTING.md](CONTRIBUTING.md) for more advice.

## License

This library and its CLI are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

## Changelog

All noteworthy changes are documented in the [CHANGELOG.md](CHANGELOG.md).
