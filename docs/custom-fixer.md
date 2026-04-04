# Creating a Custom Fixer

This guide walks through the creation of a custom auto-fixer from scratch.

---

## Overview

A fixer is a service that generates automatic corrections for issues detected by analyzers. Each fixer:

1. Checks whether it can handle a given `MigrationIssue` (`supports()`)
2. Reads the affected file and generates a corrected version (`fix()`)
3. Returns a `MigrationFix` containing the original and corrected content

The `FixEngine` orchestrates all fixers: for each issue in the report, it iterates through available fixers and collects the generated fixes. In `--fix` mode, fixes are applied to disk. In `--dry-run` mode, a unified diff is printed.

---

## Step 1: Create the Fixer Class

Create a new PHP file in `src/AutoFix/`. In this example, we create a fixer that replaces deprecated Twig filter calls in templates.

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Fixer pour le remplacement des filtres Twig deprecies.
 * Remplace sylius_price_format par money_format dans les templates Twig.
 */
final class TwigFilterFixer implements AutoFixInterface
{
    /** Nom de l'analyseur cible */
    private const TARGET_ANALYZER = 'Deprecated Twig Filter';

    /**
     * Table de correspondance entre les anciens et nouveaux filtres.
     *
     * @var array<string, string>
     */
    private const FILTER_MAPPING = [
        'sylius_price_format' => 'money_format',
        'sylius_original_price' => 'sylius_calculate_original_price|money_format',
    ];

    public function getName(): string
    {
        return 'Twig Filter Fixer';
    }

    public function supports(MigrationIssue $issue): bool
    {
        return $issue->getAnalyzer() === self::TARGET_ANALYZER;
    }

    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        $filePath = $issue->getFile();
        if ($filePath === null) {
            return null;
        }

        /* Resolution du chemin absolu */
        $absolutePath = $this->resolveAbsolutePath($filePath, $projectPath);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $originalContent = (string) file_get_contents($absolutePath);
        $fixedContent = $originalContent;

        /* Application de tous les remplacements de filtres */
        foreach (self::FILTER_MAPPING as $oldFilter => $newFilter) {
            /* Remplacement dans les expressions Twig : {{ variable|old_filter }} */
            $fixedContent = str_replace(
                '|' . $oldFilter,
                '|' . $newFilter,
                $fixedContent,
            );

            /* Remplacement dans les appels de fonction Twig */
            $fixedContent = str_replace(
                $oldFilter . '(',
                $newFilter . '(',
                $fixedContent,
            );
        }

        /* Si aucune modification n'a ete faite */
        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Remplacement des filtres Twig deprecies dans %s.',
                basename($absolutePath),
            ),
        );
    }

    /**
     * Resout le chemin absolu d'un fichier.
     */
    private function resolveAbsolutePath(
        string $filePath,
        string $projectPath,
    ): string {
        if (
            str_starts_with($filePath, '/')
            || preg_match('/^[A-Z]:/i', $filePath)
        ) {
            return $filePath;
        }

        return rtrim($projectPath, '/') . '/' . $filePath;
    }
}
```

---

## Step 2: Register the Service

Add your fixer to `config/services.yaml` with the `sylius_upgrade.fixer` tag:

```yaml
PierreArthur\SyliusUpgradeAnalyzer\AutoFix\TwigFilterFixer:
    tags: ['sylius_upgrade.fixer']
```

---

## Step 3: Write Unit Tests

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\TwigFilterFixer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

final class TwigFilterFixerTest extends TestCase
{
    private TwigFilterFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new TwigFilterFixer();
    }

    public function testGetName(): void
    {
        self::assertSame('Twig Filter Fixer', $this->fixer->getName());
    }

    public function testSupportsReturnsTrueForTargetAnalyzer(): void
    {
        $issue = new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::TWIG,
            analyzer: 'Deprecated Twig Filter',
            message: 'Deprecated filter detected',
            detail: 'Details here',
            suggestion: 'Replace the filter',
        );

        self::assertTrue($this->fixer->supports($issue));
    }

    public function testSupportsReturnsFalseForOtherAnalyzers(): void
    {
        $issue = new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: 'SwiftMailer',
            message: 'Some issue',
            detail: 'Details',
            suggestion: 'Fix it',
        );

        self::assertFalse($this->fixer->supports($issue));
    }

    public function testFixReplacesDeprecatedFilters(): void
    {
        // Create a temporary Twig file
        $tmpDir = sys_get_temp_dir() . '/sylius-test-' . uniqid();
        mkdir($tmpDir . '/templates', 0755, true);

        $templatePath = $tmpDir . '/templates/product.html.twig';
        file_put_contents($templatePath, '{{ product.price|sylius_price_format }}');

        $issue = new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::TWIG,
            analyzer: 'Deprecated Twig Filter',
            message: 'Deprecated filter',
            detail: 'Details',
            suggestion: 'Replace',
            file: 'templates/product.html.twig',
        );

        $fix = $this->fixer->fix($issue, $tmpDir);

        self::assertNotNull($fix);
        self::assertSame(FixConfidence::HIGH, $fix->confidence);
        self::assertStringContainsString('money_format', $fix->fixedContent);
        self::assertStringNotContainsString('sylius_price_format', $fix->fixedContent);

        // Cleanup
        unlink($templatePath);
        rmdir($tmpDir . '/templates');
        rmdir($tmpDir);
    }
}
```

---

## Step 4: Run the Tests

```bash
vendor/bin/phpunit tests/Unit/AutoFix/TwigFilterFixerTest.php
```

---

## Key Design Principles

### Confidence Levels

Choose the appropriate confidence level for your fixer:

| Level | When to use | Example |
|-------|-------------|---------|
| `FixConfidence::HIGH` | Deterministic, safe replacement | String renaming (`encoders:` to `password_hashers:`) |
| `FixConfidence::MEDIUM` | Likely correct, but manual review recommended | Winzou to Workflow conversion (callbacks need review) |
| `FixConfidence::LOW` | Uncertain, must be verified manually | Complex code transformations |

### Always Provide Both Contents

The `MigrationFix` object requires both `originalContent` and `fixedContent`. This allows the `FixEngine` to:

- Generate unified diffs for `--dry-run` mode
- Create backup files before applying changes
- Verify that the file hasn't been modified since analysis

### Handle Edge Cases

- **File not found:** Return `null` from `fix()` if the file doesn't exist.
- **No changes needed:** Return `null` if the fix produces identical content.
- **Multiple issues per file:** The `FixEngine` applies fixes sequentially. If your fixer modifies a file that another fixer also targets, the second fixer will see the already-modified content.

### Path Resolution

Always handle both relative and absolute paths. Use a helper method:

```php
private function resolveAbsolutePath(string $filePath, string $projectPath): string
{
    if (str_starts_with($filePath, '/') || preg_match('/^[A-Z]:/i', $filePath)) {
        return $filePath;
    }

    return rtrim($projectPath, '/') . '/' . $filePath;
}
```

---

## Existing Fixers for Reference

| Fixer | Confidence | Strategy |
|-------|------------|----------|
| `TwigHookFixer` | HIGH | Generates new YAML config file |
| `WorkflowMigrationFixer` | MEDIUM | Converts YAML structure |
| `SecurityConfigFixer` | HIGH | YAML key rename + PHP method simplification |
| `MessageBusFixer` | HIGH | String replacement in YAML/PHP |
| `CommandHandlerFixer` | HIGH | Namespace replacement in PHP |
