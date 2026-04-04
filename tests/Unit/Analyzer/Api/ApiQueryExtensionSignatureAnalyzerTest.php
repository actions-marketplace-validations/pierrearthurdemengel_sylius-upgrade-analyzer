<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Api\ApiQueryExtensionSignatureAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des signatures de QueryExtension API Platform.
 * Verifie la detection du parametre $operationName dans les classes
 * implementant QueryCollectionExtensionInterface ou QueryItemExtensionInterface.
 */
final class ApiQueryExtensionSignatureAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('apiext_', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir((string) $item->getRealPath());
            } else {
                unlink((string) $item->getRealPath());
            }
        }

        rmdir($path);
    }

    private function createReport(): MigrationReport
    {
        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12',
            targetVersion: '2.0',
            projectPath: $this->tempDir,
        );
    }

    /**
     * Verifie que supports retourne false pour un projet sans src/.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutSrcDirectory(): void
    {
        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne false pour un projet sans extension API Platform.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutExtensionInterfaces(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/SomeService.php', "<?php\nclass SomeService {}\n");

        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand une classe utilise QueryCollectionExtensionInterface.
     */
    #[Test]
    public function testSupportsReturnsTrueWithCollectionExtension(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/MyExtension.php', <<<'PHP'
<?php

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;

class MyExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection($qb, $qng, $rc, string $operationName = null): void {}
}
PHP);

        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection du parametre $operationName dans une QueryCollectionExtension.
     */
    #[Test]
    public function testDetectsOldSignatureInCollectionExtension(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ProductExtension.php', <<<'PHP'
<?php

namespace App;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

class ProductExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
    ): void {
        $queryBuilder->andWhere('o.enabled = true');
    }
}
PHP);

        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $operationIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '$operationName'),
        );
        self::assertNotEmpty($operationIssues);
    }

    /**
     * Verifie la detection du parametre $operationName dans une QueryItemExtension.
     */
    #[Test]
    public function testDetectsOldSignatureInItemExtension(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/OrderItemExtension.php', <<<'PHP'
<?php

namespace App;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

class OrderItemExtension implements QueryItemExtensionInterface
{
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        string $operationName = null,
        array $context = [],
    ): void {
        $queryBuilder->andWhere('o.enabled = true');
    }
}
PHP);

        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $operationIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '$operationName'),
        );
        self::assertNotEmpty($operationIssues);
    }

    /**
     * Verifie que les problemes sont de severite BREAKING et categorie API.
     */
    #[Test]
    public function testCreatesBreakingApiIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/MyExtension.php', <<<'PHP'
<?php

namespace App;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;

class MyExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection($qb, $qng, $rc, string $operationName = null): void {}
}
PHP);

        $analyzer = new ApiQueryExtensionSignatureAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new ApiQueryExtensionSignatureAnalyzer();

        self::assertSame('API Query Extension Signature', $analyzer->getName());
    }
}
