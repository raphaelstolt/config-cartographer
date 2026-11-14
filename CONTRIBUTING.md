# Contributing

Thank you for your interest in contributing to `config-cartographer`.

## Getting Started

After cloning the repository, follow these steps to set up the project:

```bash
git clone git@github.com:raphaelstolt/config-cartographer.git && cd config-cartographer
composer install
```

Verify the setup by running the test suites:

```bash
./vendor/bin/phpspec run
./vendor/bin/phpunit
```

## Testing

This project uses two testing frameworks:

- **PHPSpec** for unit-level behavior specifications of the domain model and collector parsing logic.
- **PHPUnit** for integration tests covering collectors against real files and CLI command output.

Run the PHPSpec unit specs:

```bash
./vendor/bin/phpspec run
```

Run the PHPUnit integration tests:

```bash
./vendor/bin/phpunit
```

Run both together:

```bash
./vendor/bin/phpspec run && ./vendor/bin/phpunit
```

## Pull Requests

Please open pull requests against the `main` branch from a dedicated feature branch. Use descriptive branch names that
reflect the change, such as:

- `feat/laravel-collector`
- `fix/dotenv-duplicate-keys`
- `docs/update-readme`

Before opening a PR, ensure:

1. Both test suites pass locally (`phpspec` and `phpunit`).
2. `CHANGELOG.md` is updated under the `[Unreleased]` section with a summary of changes.
3. All commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).

## Commit Messages

Please follow [Conventional Commits](https://www.conventionalcommits.org/) for all commit messages. This keeps the history readable
and enables automated changelog generation.

Common types used in this project:

- `feat:` — new features or behavior changes
- `fix:` — bug fixes
- `docs:` — documentation updates
- `refactor:` — code changes that neither fix bugs nor add features
- `test:` — adding or updating tests
- `chore:` — maintenance tasks (dependencies, config, etc.)
