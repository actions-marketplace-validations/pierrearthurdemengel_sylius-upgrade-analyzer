<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\DeprecatedEmailManagerAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des email managers deprecies.
 * Verifie la detection des references a OrderEmailManagerInterface et ContactEmailManagerInterface.
 */
final class DeprecatedEmailManagerAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('emailmgr_', true);
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
    public function testSupportsReturnsFalseWithoutSrcDir(): void
    {
        $analyzer = new DeprecatedEmailManagerAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand un fichier PHP reference OrderEmailManagerInterface.
     */
    #[Test]
    public function testSupportsReturnsTrueWithEmailManagerReference(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/OrderConfirmation.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;

class OrderConfirmation
{
    public function __construct(private OrderEmailManagerInterface $emailManager)
    {
    }
}
PHP);

        $analyzer = new DeprecatedEmailManagerAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte l'utilisation d'OrderEmailManagerInterface.
     */
    #[Test]
    public function testDetectsOrderEmailManagerUsage(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/OrderConfirmation.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;

class OrderConfirmation
{
    public function __construct(private OrderEmailManagerInterface $emailManager)
    {
    }
}
PHP);

        $analyzer = new DeprecatedEmailManagerAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $emailIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'OrderEmailManagerInterface'),
        );
        self::assertNotEmpty($emailIssues);
    }

    /**
     * Verifie que les problemes crees sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/OrderConfirmation.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;

class OrderConfirmation
{
    public function __construct(private OrderEmailManagerInterface $emailManager)
    {
    }
}
PHP);

        $analyzer = new DeprecatedEmailManagerAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new DeprecatedEmailManagerAnalyzer();

        self::assertSame('Deprecated Email Manager', $analyzer->getName());
    }
}
