# Creating a Custom Analyzer

This guide walks through the creation of a custom analyzer from scratch.

---

## Overview

An analyzer is a service that inspects a specific aspect of a Sylius project and reports migration issues. Each analyzer:

1. Checks whether it is applicable to the project (`supports()`)
2. Scans the project for issues (`analyze()`)
3. Adds `MigrationIssue` objects to the report

---

## Step 1: Create the Analyzer Class

Create a new PHP file in `src/Analyzer/` under the appropriate subdirectory. In this example, we create an analyzer that detects usage of a deprecated Sylius caching service.

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Analyseur de l'utilisation du service de cache deprecie.
 * Sylius 2.x remplace le service sylius.cache par le composant Cache de Symfony.
 */
final class DeprecatedCacheServiceAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par usage detecte */
    private const MINUTES_PER_USAGE = 60;

    /** URL de la documentation Symfony Cache */
    private const DOC_URL = 'https://symfony.com/doc/current/cache.html';

    /** Noms de services cibles */
    private const DEPRECATED_SERVICES = [
        'sylius.cache',
        'sylius.cache.adapter',
    ];

    public function getName(): string
    {
        return 'Deprecated Cache Service';
    }

    public function supports(MigrationReport $report): bool
    {
        /* Verification rapide : presence de fichiers PHP dans src/ */
        $srcDir = $report->getProjectPath() . '/src';

        return is_dir($srcDir);
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $usageCount = 0;

        /* Etape 1 : analyse des fichiers YAML pour les references de services */
        $usageCount += $this->analyzeYamlConfigurations($report, $projectPath);

        /* Etape 2 : analyse des fichiers PHP pour les injections de services */
        $usageCount += $this->analyzePhpUsages($report, $projectPath);

        /* Etape 3 : ajout du probleme global avec estimation */
        if ($usageCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::WARNING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d usage(s) du service de cache deprecie detecte(s)',
                    $usageCount,
                ),
                detail: 'Le service sylius.cache est deprecie dans Sylius 2.x. '
                    . 'Utiliser le composant Cache de Symfony a la place.',
                suggestion: 'Remplacer les references a sylius.cache par '
                    . 'Symfony\\Contracts\\Cache\\CacheInterface.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $usageCount * self::MINUTES_PER_USAGE,
            ));
        }
    }

    /**
     * Analyse les fichiers YAML pour les references au service deprecie.
     */
    private function analyzeYamlConfigurations(
        MigrationReport $report,
        string $projectPath,
    ): int {
        $configDir = $projectPath . '/config';
        if (!is_dir($configDir)) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($configDir)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $content = (string) file_get_contents($file->getRealPath());

            foreach (self::DEPRECATED_SERVICES as $service) {
                if (str_contains($content, $service)) {
                    $count++;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::WARNING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Reference au service "%s" detectee dans %s',
                            $service,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le service "%s" est deprecie dans Sylius 2.x.',
                            $service,
                        ),
                        suggestion: 'Remplacer par Symfony\\Contracts\\Cache\\CacheInterface.',
                        file: $file->getRealPath(),
                        docUrl: self::DOC_URL,
                    ));
                }
            }
        }

        return $count;
    }

    /**
     * Analyse les fichiers PHP pour les usages du service de cache.
     */
    private function analyzePhpUsages(
        MigrationReport $report,
        string $projectPath,
    ): int {
        $srcDir = $projectPath . '/src';
        if (!is_dir($srcDir)) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        foreach ($finder as $file) {
            $content = (string) file_get_contents($file->getRealPath());

            foreach (self::DEPRECATED_SERVICES as $service) {
                if (str_contains($content, $service)) {
                    $count++;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::WARNING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Usage du service "%s" detecte dans %s',
                            $service,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le fichier %s reference le service deprecie "%s".',
                            $file->getRelativePathname(),
                            $service,
                        ),
                        suggestion: 'Injecter CacheInterface via autowiring au lieu '
                            . 'de referencer le service directement.',
                        file: $file->getRealPath(),
                        docUrl: self::DOC_URL,
                    ));
                }
            }
        }

        return $count;
    }
}
```

---

## Step 2: Register the Service

Add your analyzer to `config/services.yaml` with the `sylius_upgrade.analyzer` tag:

```yaml
PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\DeprecatedCacheServiceAnalyzer:
    tags: ['sylius_upgrade.analyzer']
```

The `AnalyzerCompilerPass` collects all services tagged with `sylius_upgrade.analyzer` and injects them into the `AnalyzeCommand`.

---

## Step 3: Create a Test Fixture

If your analyzer needs specific files to detect, add them to an appropriate fixture project in `tests/Fixtures/`. For example, create a YAML config:

```yaml
# tests/Fixtures/project-complex/config/services/cache.yaml
services:
    App\Cache\ProductCacheWarmer:
        arguments:
            $cache: '@sylius.cache'
```

---

## Step 4: Write Unit Tests

Create a test class in `tests/Unit/Analyzer/Deprecation/`:

```php
<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\DeprecatedCacheServiceAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

final class DeprecatedCacheServiceAnalyzerTest extends TestCase
{
    private DeprecatedCacheServiceAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new DeprecatedCacheServiceAnalyzer();
    }

    public function testGetName(): void
    {
        self::assertSame('Deprecated Cache Service', $this->analyzer->getName());
    }

    public function testSupportsReturnsTrueWhenSrcDirExists(): void
    {
        $report = new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12.0',
            targetVersion: '2.2',
            projectPath: __DIR__ . '/../../../Fixtures/project-complex',
        );

        self::assertTrue($this->analyzer->supports($report));
    }

    public function testAnalyzeDetectsDeprecatedCacheService(): void
    {
        $report = new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12.0',
            targetVersion: '2.2',
            projectPath: __DIR__ . '/../../../Fixtures/project-complex',
        );

        $this->analyzer->analyze($report);

        // Assert issues were found (depends on fixture content)
        $issues = $report->getIssues();
        // Add specific assertions based on your fixture
    }
}
```

---

## Step 5: Run the Tests

```bash
vendor/bin/phpunit tests/Unit/Analyzer/Deprecation/DeprecatedCacheServiceAnalyzerTest.php
```

---

## Key Design Principles

1. **`supports()` must be fast.** Only check for file/directory existence or `composer.json` keys. Never parse PHP code in `supports()`.

2. **Create fine-grained issues.** One `MigrationIssue` per specific occurrence (file + line), plus one summary issue with the total estimation.

3. **Provide actionable suggestions.** Every issue should have a concrete `suggestion` telling the developer what to do.

4. **Include documentation URLs.** Link to official Sylius or Symfony docs whenever possible.

5. **Estimate conservatively.** It is better to overestimate than to give developers a false sense of ease.

6. **Use `nikic/php-parser` for PHP analysis.** For anything beyond simple string matching, use the AST parser. See `WinzouStateMachineAnalyzer` for a complete example with node visitors.

---

## Advanced: Using PHP-Parser

For analyzers that need to detect specific PHP patterns (class inheritance, method calls, annotations), use `nikic/php-parser`:

```php
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($code);

$visitor = new class extends NodeVisitorAbstract {
    /** @var list<array{class: string, line: int}> */
    public array $findings = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Class_ && $node->extends !== null) {
            $parentName = $node->extends->toString();
            if (str_contains($parentName, 'TargetClass')) {
                $this->findings[] = [
                    'class' => $node->name?->name ?? 'Anonymous',
                    'line' => $node->getStartLine(),
                ];
            }
        }

        return null;
    }
};

$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);
```
