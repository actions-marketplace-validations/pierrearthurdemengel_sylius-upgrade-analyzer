<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\RemovedPaymentGatewayAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des passerelles de paiement supprimees.
 * Verifie la detection de payum/stripe et payum/paypal-express-checkout.
 */
final class RemovedPaymentGatewayAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('gateway_', true);
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
        $analyzer = new RemovedPaymentGatewayAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne false quand composer.json ne contient pas les packages.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutPayum(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => ['sylius/sylius' => '^1.12'],
        ]));

        $analyzer = new RemovedPaymentGatewayAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand composer.json contient payum/stripe.
     */
    #[Test]
    public function testSupportsReturnsTrueWithPayumStripe(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'sylius/sylius' => '^1.12',
                'payum/stripe' => '^1.0',
            ],
        ]));

        $analyzer = new RemovedPaymentGatewayAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de payum/stripe dans composer.json.
     */
    #[Test]
    public function testDetectsPayumStripePackage(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'payum/stripe' => '^1.0',
            ],
        ]));

        $analyzer = new RemovedPaymentGatewayAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $stripeIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'payum/stripe'),
        );
        self::assertNotEmpty($stripeIssues);
    }

    /**
     * Verifie que les problemes sont de severite BREAKING.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => [
                'payum/stripe' => '^1.0',
            ],
        ]));

        $analyzer = new RemovedPaymentGatewayAnalyzer();
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
        $analyzer = new RemovedPaymentGatewayAnalyzer();

        self::assertSame('Removed Payment Gateway', $analyzer->getName());
    }
}
