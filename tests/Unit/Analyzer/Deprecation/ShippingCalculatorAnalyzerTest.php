<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\ShippingCalculatorAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des calculateurs d'expedition.
 * Verifie la detection des implementations de CalculatorInterface de Sylius Shipping.
 */
final class ShippingCalculatorAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('shipcalc_', true);
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
        $analyzer = new ShippingCalculatorAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand CalculatorInterface de Sylius Shipping est reference.
     */
    #[Test]
    public function testSupportsReturnsTrueWithShippingCalculator(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/WeightCalculator.php', <<<'PHP'
<?php

namespace App\Shipping;

use Sylius\Component\Shipping\Calculator\CalculatorInterface;

class WeightCalculator implements CalculatorInterface
{
    public function calculate($subject, array $configuration): int { return 0; }
    public function getType(): string { return 'weight'; }
}
PHP);

        $analyzer = new ShippingCalculatorAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection d'un calculateur personnalise.
     */
    #[Test]
    public function testDetectsShippingCalculatorImplementation(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/WeightCalculator.php', <<<'PHP'
<?php

namespace App\Shipping;

use Sylius\Component\Shipping\Calculator\CalculatorInterface;

class WeightCalculator implements CalculatorInterface
{
    public function calculate($subject, array $configuration): int { return 0; }
    public function getType(): string { return 'weight'; }
}
PHP);

        $analyzer = new ShippingCalculatorAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $calcIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'WeightCalculator'),
        );
        self::assertNotEmpty($calcIssues);
    }

    /**
     * Verifie que les problemes sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/WeightCalculator.php', <<<'PHP'
<?php

namespace App\Shipping;

use Sylius\Component\Shipping\Calculator\CalculatorInterface;

class WeightCalculator implements CalculatorInterface
{
    public function calculate($subject, array $configuration): int { return 0; }
    public function getType(): string { return 'weight'; }
}
PHP);

        $analyzer = new ShippingCalculatorAnalyzer();
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
        $analyzer = new ShippingCalculatorAnalyzer();

        self::assertSame('Shipping Calculator', $analyzer->getName());
    }
}
