# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-15

### Added

- **CLI tool** with three commands: `scan`, `trace`, and `graph`
- **DotenvCollector**: discovers `.env` and `.env.example` key declarations
- **SymfonyYamlCollector**: detects `%env(NAME)%` and `%env(resolve:NAME)%` patterns in YAML config
- **PhpEnvironmentCollector**: finds `getenv()`, `$_ENV[]`, and `$_SERVER[]` references via PHP AST analysis
- **Evidence-backed graph** with immutable domain model (Node, Edge, DependencyGraph)
- **Confidence levels**: `observed`, `inferred`, `unresolved` for every relationship
- **Deterministic JSON output** for all commands
- **Mermaid flowchart** generation for visual dependency graphs
- **Secret-safe design**: never displays, persists, or exports secret values
- **Symlink protection**: refuses to follow symlinks outside the project root
- **PHPSpec 9.0** behavior specifications with 56 passing tests
