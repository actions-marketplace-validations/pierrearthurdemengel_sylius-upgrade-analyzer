# Sylius Upgrade Analyzer

[![Packagist Version](https://img.shields.io/packagist/v/pierre-arthur/sylius-upgrade-analyzer)](https://packagist.org/packages/pierre-arthur/sylius-upgrade-analyzer)
[![PHP Version](https://img.shields.io/packagist/php-v/pierre-arthur/sylius-upgrade-analyzer)](https://packagist.org/packages/pierre-arthur/sylius-upgrade-analyzer)
[![Symfony Version](https://img.shields.io/badge/symfony-6.4%20%7C%207.2-blue)](https://symfony.com)
[![CI](https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer/actions/workflows/ci.yaml/badge.svg)](https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer/actions/workflows/ci.yaml)
[![License](https://img.shields.io/packagist/l/pierre-arthur/sylius-upgrade-analyzer)](LICENSE)

**Automated migration audit CLI for Sylius 1.x to 2.x projects.**

Sylius Upgrade Analyzer scans your existing Sylius 1.x project, detects every breaking change, deprecated API, and incompatible pattern, then produces a detailed migration report with time estimates, fix suggestions, and (where possible) automatic corrections.

---

## Features

### 26 Built-in Analyzers

| # | Analyzer | Category | What it detects |
|---|----------|----------|-----------------|
| 1 | Twig Template Override | Twig | Overridden Sylius templates that must migrate to Twig hooks |
| 2 | Winzou State Machine | Deprecation | winzou/state-machine-bundle usage to replace with Symfony Workflow |
| 3 | SwiftMailer | Deprecation | swiftmailer/swiftmailer dependency and usages |
| 4 | User Encoder | Deprecation | Deprecated `security.encoders` config and `getSalt()` methods |
| 5 | Payum | Deprecation | Payum payment gateways to migrate to Payment Requests |
| 6 | Plugin Compatibility | Plugin | Sylius plugins compatibility with target version |
| 7 | Grid Customization | Grid | Custom grid configurations needing adaptation |
| 8 | Resource Bundle | Resource | SyliusResourceBundle configuration changes |
| 9 | Semantic UI | Frontend | Semantic UI CSS framework usage (removed in Sylius 2.x) |
| 10 | jQuery | Frontend | jQuery usage and Semantic UI JavaScript dependencies |
| 11 | Webpack Encore | Frontend | Webpack Encore configuration needing updates |
| 12 | API Platform Migration | API | API Platform 2.x to 3.x breaking changes |
| 13 | Message Bus Rename | Deprecation | Renamed message bus services (`sylius_default.bus` to `sylius.command_bus`) |
| 14 | Command Handler Rename | Deprecation | `src/Message/` directory to rename to `src/Command/` |
| 15 | Deprecated Email Manager | Deprecation | Deprecated email manager service usage |
| 16 | Removed Payment Gateway | Deprecation | Payment gateways removed from Sylius core |
| 17 | Service Decorator | Deprecation | Service decorators using deprecated patterns |
| 18 | Order Processor Priority | Deprecation | Order processor priority changes |
| 19 | Form Type Extension Priority | Deprecation | Form type extension priority parameter changes |
| 20 | Behat Context Deprecation | Deprecation | Deprecated Behat contexts and step definitions |
| 21 | Admin Menu Event | Deprecation | Deprecated admin menu event system |
| 22 | Translation Key | Deprecation | Renamed `sylius.*` translation keys |
| 23 | Promotion Rule Checker | Deprecation | Deprecated promotion rule checker interface changes |
| 24 | Shipping Calculator | Deprecation | Shipping calculator interface changes |
| 25 | Doctrine XML Mapping | Deprecation | Doctrine XML mapping files requiring updates |
| 26 | Custom Fixture | Deprecation | Custom fixture loader deprecations |
| 27 | Multi-Store Channel | Deprecation | Multi-store channel configuration changes |

### 5 Output Reporters

- **Console** -- Rich terminal output with ASCII gauge, colored severity levels, category breakdown
- **JSON** -- Machine-readable structured report
- **SARIF** -- Static Analysis Results Interchange Format (GitHub Code Scanning compatible)
- **Markdown** -- Human-readable report with tables, suitable for PRs and wikis
- **PDF** -- Professional report for stakeholders (via `--pdf` flag)

### 5 Auto-Fixers

| Fixer | What it fixes |
|-------|---------------|
| Twig Hook Fixer | Generates `sylius_twig_hooks` YAML config for template overrides |
| Workflow Migration Fixer | Converts winzou state machine YAML to Symfony Workflow config |
| Security Config Fixer | Replaces `security.encoders` with `security.password_hashers` and simplifies `getSalt()` |
| Message Bus Fixer | Renames bus references in YAML and PHP files |
| Command Handler Fixer | Updates namespaces from `Message\` to `Command\` |

### GitHub Action

Run the analyzer in your CI pipeline, post PR comments, and upload SARIF to GitHub Code Scanning.

### Additional Features

- **Custom rules** via `.sylius-upgrade-rules.yaml`
- **Baseline management** -- save and diff results across runs
- **Sprint planner** -- generate a migration roadmap with sprint breakdown
- **Plugin compatibility** -- checks Sylius Addons Marketplace and Packagist

---

## Installation

```bash
composer require --dev pierre-arthur/sylius-upgrade-analyzer
```

Requirements: PHP 8.2+, Symfony 6.4 or 7.2.

---

## Usage

### Basic Analysis

```bash
# Analyze the current directory
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze

# Analyze a specific project
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze /path/to/sylius-project

# Target a specific Sylius version
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --target-version=2.2
```

### Output Formats

```bash
# Console output (default)
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze

# JSON report
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --format=json --output=report.json

# SARIF report (for GitHub Code Scanning)
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --format=sarif --output=report.sarif

# Markdown report
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --format=markdown --output=report.md

# PDF report
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --pdf
```

### Filtering Analyzers

```bash
# Run only specific analyzers
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --only="Twig Template Override" --only="Payum"
```

### Verbose Output

```bash
# Show warnings
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze -v

# Show warnings and suggestions
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze -vv
```

### Offline Mode

```bash
# Skip marketplace compatibility checks
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --no-marketplace
```

### Custom Rules

```bash
# Use a custom rules file
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --rules=.sylius-upgrade-rules.yaml
```

### Baseline Management

```bash
# Save a baseline
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --save-baseline

# Compare with previous baseline
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --diff
```

### Sprint Planning

```bash
# Generate a sprint plan with team velocity of 40h/sprint
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --sprint-plan --velocity=40
```

---

## Auto-Fix

The analyzer can automatically fix certain issues:

```bash
# Apply all available fixes
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --fix

# Preview fixes without modifying files
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --fix --dry-run
```

Each fix has a confidence level:

- **HIGH** -- Safe to apply automatically (e.g., renaming `security.encoders` to `security.password_hashers`)
- **MEDIUM** -- Likely correct but manual review recommended (e.g., workflow migration)
- **LOW** -- Uncertain, manual verification required

In `--dry-run` mode, the tool generates a unified diff patch showing what would change without writing any files.

---

## GitHub Action

Add the analyzer to your CI workflow:

```yaml
name: Sylius Migration Audit
on: [push, pull_request]

jobs:
  analyze:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: pierrearthurdemengel/sylius-upgrade-analyzer@v1
        with:
          project-path: '.'
          target-version: '2.2'
          fail-on-breaking: 'true'
          upload-sarif: 'true'
          post-pr-comment: 'true'
```

### Action Inputs

| Input | Default | Description |
|-------|---------|-------------|
| `project-path` | `.` | Path to the Sylius project |
| `target-version` | `2.2` | Target Sylius version |
| `fail-on-breaking` | `true` | Fail the job if breaking issues are found |
| `upload-sarif` | `false` | Upload SARIF report to GitHub Code Scanning |
| `post-pr-comment` | `false` | Post a summary comment on the PR |

### Action Outputs

| Output | Description |
|--------|-------------|
| `complexity` | Detected complexity level (trivial, moderate, complex, major) |
| `total-hours` | Estimated total hours |
| `breaking-count` | Number of breaking issues |

---

## Custom Rules

Create a `.sylius-upgrade-rules.yaml` file at the root of your project:

```yaml
rules:
  - name: legacy_payment_service
    type: php_class_usage
    pattern: 'App\\Service\\LegacyPaymentService'
    severity: breaking
    category: deprecation
    message: 'Legacy payment service must be replaced'
    suggestion: 'Migrate to the new PaymentProcessor service'
    estimated_minutes: 120

  - name: old_twig_filter
    type: twig_function
    pattern: 'sylius_price_format'
    severity: warning
    category: twig
    message: 'Deprecated Twig filter detected'
    suggestion: 'Use the new money_format filter instead'
    estimated_minutes: 15
```

Supported rule types: `php_class_usage`, `php_method_call`, `twig_function`, `yaml_key`.

See [docs/custom-rules.md](docs/custom-rules.md) for the full reference.

---

## Example Output

### Console

```
 Sylius Upgrade Analyzer - Migration Report
 ===========================================

 Projet        : /home/dev/my-sylius-shop
 Version       : 1.12.18
 Cible         : 2.2
 Date          : 04/04/2026 14:32:07

 Resume global
 -------------
   Problemes critiques (BREAKING) : 12
   Avertissements (WARNING) :        23
   Suggestions :                      8

   Temps total estime : 142.5 heures

   Complexite globale : COMPLEXE

   [████████████████████████████░░░░░░░░░░░░░] 142.5h / COMPLEX

 Estimation par categorie
 -------------------------
 +-----------------+--------------+-----------------+
 | Categorie       | Nb problemes | Heures estimees |
 +-----------------+--------------+-----------------+
 | Deprecations    | 18           | 64.0 h          |
 | Templates Twig  | 7            | 35.0 h          |
 | Front-end       | 6            | 24.0 h          |
 | Plugins         | 4            | 16.0 h          |
 | API             | 2            | 3.5 h           |
 +-----------------+--------------+-----------------+
```

### JSON

```json
{
  "summary": {
    "complexity": "complex",
    "total_hours": 142.5,
    "breaking_count": 12,
    "warning_count": 23,
    "suggestion_count": 8,
    "detected_version": "1.12.18",
    "target_version": "2.2"
  },
  "hours_by_category": {
    "deprecation": 64.0,
    "twig": 35.0,
    "frontend": 24.0,
    "plugin": 16.0,
    "api": 3.5
  },
  "issues": [
    {
      "severity": "breaking",
      "category": "deprecation",
      "analyzer": "Winzou State Machine",
      "message": "3 machine(s) a etats winzou detectee(s) necessitant une migration vers Symfony Workflow",
      "detail": "Chaque machine a etats doit etre convertie en definition Symfony Workflow.",
      "suggestion": "Migrer chaque definition winzou_state_machine vers framework.workflows.",
      "estimated_minutes": 720
    }
  ]
}
```

---

## Compatibility Matrix

Check the compatibility matrix for Sylius plugins:

```bash
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:compatibility-matrix
```

---

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:

- Creating custom analyzers
- Creating custom fixers
- Coding standards and test requirements
- PR process

---

## Documentation

- [All Analyzers Reference](docs/analyzers.md)
- [Creating a Custom Analyzer](docs/custom-analyzer.md)
- [Creating a Custom Fixer](docs/custom-fixer.md)
- [Custom Rules Reference](docs/custom-rules.md)
- [Migration Guide: From Detection to Resolution](docs/migration-guide.md)

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Author

**Pierre-Arthur Demengel** -- [pierrearthur.demengel@gmail.com](mailto:pierrearthur.demengel@gmail.com)

GitHub: [https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer](https://github.com/pierrearthurdemengel/sylius-upgrade-analyzer)
