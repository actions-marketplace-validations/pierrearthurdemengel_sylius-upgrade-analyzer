<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\DeprecatedBundlePackageAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des paquets deprecies ou supprimes.
 * Verifie la detection des dependances obsoletes dans composer.json.
 */
final class DeprecatedBundlePackageAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('depbundle_', true);
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
     * Verifie que supports retourne false sans composer.json.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutComposerJson(): void
    {
        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne false sans paquets deprecies.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutDeprecatedPackages(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'sylius/sylius' => '^1.12',
                'symfony/framework-bundle' => '^6.0',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand sylius/calendar est present.
     */
    #[Test]
    public function testSupportsReturnsTrueWithSyliusCalendar(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'sylius/sylius' => '^1.12',
                'sylius/calendar' => '^0.3',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de friendsofsymfony/rest-bundle.
     */
    #[Test]
    public function testDetectsFosRestBundle(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'friendsofsymfony/rest-bundle' => '^3.0',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $fosIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'friendsofsymfony/rest-bundle'),
        );
        self::assertNotEmpty($fosIssues);
    }

    /**
     * Verifie la detection de stripe/stripe-php.
     */
    #[Test]
    public function testDetectsStripePhp(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'stripe/stripe-php' => '^10.0',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $stripeIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'stripe/stripe-php'),
        );
        self::assertNotEmpty($stripeIssues);
    }

    /**
     * Verifie la detection de plusieurs paquets deprecies simultanement.
     */
    #[Test]
    public function testDetectsMultipleDeprecatedPackages(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'friendsofsymfony/rest-bundle' => '^3.0',
                'jms/serializer-bundle' => '^4.0',
                'sylius/calendar' => '^0.3',
                'stripe/stripe-php' => '^10.0',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        /* 4 paquets + 1 resume global = 5 problemes */
        $packageIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'detecte'),
        );
        self::assertGreaterThanOrEqual(4, count($packageIssues));
    }

    /**
     * Verifie que les problemes sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testCreatesBreakingDeprecationIssues(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'sylius/calendar' => '^0.3',
            ],
        ]));

        $analyzer = new DeprecatedBundlePackageAnalyzer();
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
        $analyzer = new DeprecatedBundlePackageAnalyzer();

        self::assertSame('Deprecated Bundle Package', $analyzer->getName());
    }
}
