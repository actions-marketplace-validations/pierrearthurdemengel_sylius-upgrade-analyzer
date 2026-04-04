# Contributing to Sylius Upgrade Analyzer

Thank you for considering contributing to Sylius Upgrade Analyzer! This document outlines the process and conventions to follow.

---

## Table of Contents

- [Getting Started](#getting-started)
- [Creating a Custom Analyzer](#creating-a-custom-analyzer)
- [Creating a Custom Fixer](#creating-a-custom-fixer)
- [Coding Standards](#coding-standards)
- [Test Requirements](#test-requirements)
- [Commit Message Format](#commit-message-format)
- [Pull Request Process](#pull-request-process)

---

## Getting Started

1. Fork the repository on GitHub.
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/sylius-upgrade-analyzer.git
   cd sylius-upgrade-analyzer
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Run the test suite to confirm everything works:
   ```bash
   make test
   ```

---

## Creating a Custom Analyzer

All analyzers implement `PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface`:

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;

interface AnalyzerInterface
{
    /** Executes the analysis and adds detected issues to the report. */
    public function analyze(MigrationReport $report): void;

    /** Returns the human-readable analyzer name. */
    public function getName(): string;

    /** Determines if this analyzer is applicable to the project being analyzed. */
    public function supports(MigrationReport $report): bool;
}
```

### Steps

1. Create your analyzer class in `src/Analyzer/` under the appropriate subdirectory.
2. Implement `AnalyzerInterface`.
3. Register it in `config/services.yaml` with the `sylius_upgrade.analyzer` tag:
   ```yaml
   PierreArthur\SyliusUpgradeAnalyzer\Analyzer\YourNamespace\YourAnalyzer:
       tags: ['sylius_upgrade.analyzer']
   ```
4. Add a test fixture in `tests/Fixtures/` if needed.
5. Write a corresponding unit test in `tests/Unit/Analyzer/`.

### Conventions

- The `getName()` method must return a unique, human-readable name (English).
- The `supports()` method should be fast -- only check for the existence of files or dependencies, do not parse code.
- The `analyze()` method should create `MigrationIssue` objects with:
  - Appropriate `Severity` (BREAKING, WARNING, or SUGGESTION)
  - Appropriate `Category` (TWIG, DEPRECATION, PLUGIN, GRID, RESOURCE, FRONTEND, API)
  - A clear `message` describing the problem
  - A `detail` explaining the technical context
  - A `suggestion` with actionable advice
  - An `estimatedMinutes` value for the time estimate
  - A `docUrl` linking to the official Sylius/Symfony documentation when available
  - The `file` and `line` where the issue was found, when applicable

See [docs/custom-analyzer.md](docs/custom-analyzer.md) for a complete step-by-step guide with code examples.

---

## Creating a Custom Fixer

All fixers implement `PierreArthur\SyliusUpgradeAnalyzer\AutoFix\AutoFixInterface`:

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

interface AutoFixInterface
{
    public function getName(): string;
    public function supports(MigrationIssue $issue): bool;
    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix;
}
```

### Steps

1. Create your fixer class in `src/AutoFix/`.
2. Implement `AutoFixInterface`.
3. Register it in `config/services.yaml` with the `sylius_upgrade.fixer` tag.
4. Write unit tests in `tests/Unit/AutoFix/`.

### Conventions

- The `supports()` method should check `$issue->getAnalyzer()` to target the correct analyzer.
- The `fix()` method returns a `MigrationFix` with:
  - `FixConfidence::HIGH` for safe, deterministic fixes
  - `FixConfidence::MEDIUM` for probable fixes requiring review
  - `FixConfidence::LOW` for uncertain fixes
- Always provide the original content and the fixed content so the engine can generate diffs.

See [docs/custom-fixer.md](docs/custom-fixer.md) for a complete step-by-step guide.

---

## Coding Standards

### PHP

- **PSR-12** coding standard, enforced by PHP-CS-Fixer.
- **PHPStan level 8** -- no errors allowed.
- **Comments in French**, code identifiers (class names, method names, variables) **in English**.
- `declare(strict_types=1)` in every PHP file.
- Use `final` on classes that are not designed for inheritance.
- Use `readonly` on immutable value objects.
- Enum-backed types for all finite sets (Severity, Category, Complexity, etc.).

### Running Checks

```bash
# PHP-CS-Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff

# PHPStan
vendor/bin/phpstan analyse src --level=8

# Fix code style automatically
vendor/bin/php-cs-fixer fix
```

---

## Test Requirements

- **PHPUnit 11** is the test framework.
- All analyzers and fixers must have corresponding unit tests.
- Use fixture projects in `tests/Fixtures/` to test against realistic project structures.
- **Minimum 80% code coverage** on new code.
- Test naming convention: `test<MethodName><Scenario>` (e.g., `testAnalyzeDetectsSwiftMailerDependency`).

### Running Tests

```bash
# Full suite
vendor/bin/phpunit

# With coverage report
vendor/bin/phpunit --coverage-text

# Specific test file
vendor/bin/phpunit tests/Unit/Analyzer/Deprecation/SwiftMailerAnalyzerTest.php
```

### Fixture Projects

The `tests/Fixtures/` directory contains several fixture projects of increasing complexity:

| Fixture | Description |
|---------|-------------|
| `project-trivial` | Minimal project with almost no issues |
| `project-moderate` | Some deprecated dependencies |
| `project-complex` | Multiple deprecated patterns, plugin issues, template overrides |
| `project-major` | Extreme case with all possible issues for maximum coverage |

When adding a new analyzer, add the relevant files to the appropriate fixture project(s).

---

## Commit Message Format

This project follows **Conventional Commits**:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature (analyzer, fixer, reporter) |
| `fix` | Bug fix |
| `docs` | Documentation changes |
| `test` | Adding or updating tests |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `chore` | Build, CI, or tooling changes |
| `style` | Code style changes (formatting, missing semicolons) |

### Examples

```
feat(analyzer): add DoctrineXmlMappingAnalyzer

Detects Doctrine XML mapping files that require updates
for Sylius 2.x compatibility.

Closes #42
```

```
fix(fixer): handle empty security.yaml in SecurityConfigFixer

The fixer would crash if security.yaml existed but was empty.
Now returns null gracefully.
```

```
test(fixture): add project-major fixture with all edge cases
```

---

## Pull Request Process

1. **Create a branch** from `main` with a descriptive name:
   ```bash
   git checkout -b feat/my-new-analyzer
   ```

2. **Make your changes** following the coding standards above.

3. **Ensure all checks pass**:
   ```bash
   vendor/bin/phpstan analyse src --level=8
   vendor/bin/php-cs-fixer fix --dry-run --diff
   vendor/bin/phpunit --coverage-text
   ```

4. **Push your branch** and open a Pull Request against `main`.

5. **Fill in the PR template** with:
   - A clear description of what the PR does
   - How to test the changes
   - Any related issues

6. **Wait for review**. A maintainer will review and may request changes.

7. **After approval**, the PR will be squash-merged into `main`.

### PR Checklist

- [ ] PHPStan level 8 passes
- [ ] PHP-CS-Fixer passes
- [ ] PHPUnit tests pass with 80%+ coverage on new code
- [ ] New analyzer/fixer has corresponding unit tests
- [ ] Fixture projects updated if needed
- [ ] `config/services.yaml` updated if a new service was added
- [ ] Documentation updated (if applicable)

---

## Questions?

Open an issue on [GitHub](https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer/issues) or reach out to [pierrearthur.demengel@gmail.com](mailto:pierrearthur.demengel@gmail.com).
