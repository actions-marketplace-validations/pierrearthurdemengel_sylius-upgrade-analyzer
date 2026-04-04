<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\ServiceVisibilityAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de la visibilite des services Sylius.
 * Verifie la detection des acces directs au conteneur pour les services Sylius.
 */
final class ServiceVisibilityAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('svcvis_', true);
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
        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne false pour un projet sans acces direct au conteneur.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutContainerAccess(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/CleanService.php', "<?php\nclass CleanService {}\n");

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand $this->get('sylius.') est utilise.
     */
    #[Test]
    public function testSupportsReturnsTrueWithThisGet(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/LegacyController.php', <<<'PHP'
<?php

class LegacyController
{
    public function indexAction(): void
    {
        $repo = $this->get('sylius.repository.product');
    }
}
PHP);

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de $this->get('sylius.').
     */
    #[Test]
    public function testDetectsThisGetSylius(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ShopController.php', <<<'PHP'
<?php

namespace App\Controller;

class ShopController
{
    public function indexAction(): void
    {
        $channelContext = $this->get('sylius.context.channel');
    }
}
PHP);

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $accessIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'conteneur'),
        );
        self::assertNotEmpty($accessIssues);
    }

    /**
     * Verifie la detection de $this->container->get('sylius.').
     */
    #[Test]
    public function testDetectsThisContainerGetSylius(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/AdminController.php', <<<'PHP'
<?php

namespace App\Controller;

class AdminController
{
    public function dashboardAction(): void
    {
        $orderRepo = $this->container->get('sylius.repository.order');
    }
}
PHP);

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $accessIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '$this->container->get'),
        );
        self::assertNotEmpty($accessIssues);
    }

    /**
     * Verifie la detection de $container->get('sylius.').
     */
    #[Test]
    public function testDetectsContainerGetSylius(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ServiceLocator.php', <<<'PHP'
<?php

namespace App\Service;

class ServiceLocator
{
    public function getProduct($container): object
    {
        return $container->get('sylius.manager.product');
    }
}
PHP);

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $accessIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '$container->get'),
        );
        self::assertNotEmpty($accessIssues);
    }

    /**
     * Verifie que les problemes sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testCreatesBreakingDeprecationIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/Controller.php', <<<'PHP'
<?php

class Controller
{
    public function action(): void
    {
        $this->get('sylius.context.channel');
    }
}
PHP);

        $analyzer = new ServiceVisibilityAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new ServiceVisibilityAnalyzer();

        self::assertSame('Service Visibility', $analyzer->getName());
    }
}
