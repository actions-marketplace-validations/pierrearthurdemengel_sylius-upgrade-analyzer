<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\PromotionRuleCheckerAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des regles de promotion.
 * Verifie la detection des implementations de PromotionRuleCheckerInterface
 * et PromotionActionCommandInterface.
 */
final class PromotionRuleCheckerAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('promorule_', true);
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
        $analyzer = new PromotionRuleCheckerAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand PromotionRuleCheckerInterface est reference.
     */
    #[Test]
    public function testSupportsReturnsTrueWithPromotionRuleChecker(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/CustomRuleChecker.php', <<<'PHP'
<?php

namespace App\Promotion;

use Sylius\Component\Promotion\Checker\Rule\PromotionRuleCheckerInterface;

class CustomRuleChecker implements PromotionRuleCheckerInterface
{
    public function isEligible($subject, array $configuration): bool { return true; }
    public function getType(): string { return 'custom'; }
}
PHP);

        $analyzer = new PromotionRuleCheckerAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection d'une classe implementant PromotionRuleCheckerInterface.
     */
    #[Test]
    public function testDetectsPromotionRuleCheckerImplementation(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/CustomRuleChecker.php', <<<'PHP'
<?php

namespace App\Promotion;

use Sylius\Component\Promotion\Checker\Rule\PromotionRuleCheckerInterface;

class CustomRuleChecker implements PromotionRuleCheckerInterface
{
    public function isEligible($subject, array $configuration): bool { return true; }
    public function getType(): string { return 'custom'; }
}
PHP);

        $analyzer = new PromotionRuleCheckerAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $ruleIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'CustomRuleChecker'),
        );
        self::assertNotEmpty($ruleIssues);
    }

    /**
     * Verifie que les problemes sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/CustomRuleChecker.php', <<<'PHP'
<?php

namespace App\Promotion;

use Sylius\Component\Promotion\Checker\Rule\PromotionRuleCheckerInterface;

class CustomRuleChecker implements PromotionRuleCheckerInterface
{
    public function isEligible($subject, array $configuration): bool { return true; }
    public function getType(): string { return 'custom'; }
}
PHP);

        $analyzer = new PromotionRuleCheckerAnalyzer();
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
        $analyzer = new PromotionRuleCheckerAnalyzer();

        self::assertSame('Promotion Rule Checker', $analyzer->getName());
    }
}
