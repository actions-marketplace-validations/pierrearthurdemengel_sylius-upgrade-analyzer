<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\PaymentRequestEnvAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des variables d'environnement Payment Request.
 * Verifie la detection des variables manquantes et de la configuration messenger.
 */
final class PaymentRequestEnvAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('payreqenv_', true);
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
     * Verifie que supports retourne false pour un projet sans fichier .env ni messenger.yaml.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutEnvOrMessenger(): void
    {
        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand un fichier .env existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWithEnvFile(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=dev\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand messenger.yaml existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWithMessengerYaml(): void
    {
        $configDir = $this->tempDir . '/config/packages';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', "framework:\n    messenger: ~\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection des deux variables manquantes dans un .env vide.
     */
    #[Test]
    public function testDetectsMissingEnvVars(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=dev\nDATABASE_URL=mysql://root@localhost\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $dsnIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'SYLIUS_MESSENGER_TRANSPORT_PAYMENT_REQUEST_DSN'),
        );
        $failedDsnIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'SYLIUS_MESSENGER_TRANSPORT_PAYMENT_REQUEST_FAILED_DSN'),
        );

        self::assertNotEmpty($dsnIssues);
        self::assertNotEmpty($failedDsnIssues);
    }

    /**
     * Verifie qu'aucun probleme n'est leve quand les deux variables sont presentes.
     */
    #[Test]
    public function testNoIssueWhenAllEnvVarsPresent(): void
    {
        file_put_contents($this->tempDir . '/.env', implode("\n", [
            'APP_ENV=dev',
            'SYLIUS_MESSENGER_TRANSPORT_PAYMENT_REQUEST_DSN=doctrine://default',
            'SYLIUS_MESSENGER_TRANSPORT_PAYMENT_REQUEST_FAILED_DSN=doctrine://default?queue_name=failed',
        ]));
        $configDir = $this->tempDir . '/config/packages';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', "framework:\n    messenger:\n        transports:\n            sylius_payment_request:\n                dsn: test\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $envIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'SYLIUS_MESSENGER_TRANSPORT'),
        );
        self::assertEmpty($envIssues);
    }

    /**
     * Verifie la detection de la configuration payment_request manquante dans messenger.yaml.
     */
    #[Test]
    public function testDetectsMissingPaymentRequestTransport(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=dev\n");
        $configDir = $this->tempDir . '/config/packages';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', "framework:\n    messenger:\n        default_bus: sylius_default.bus\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $transportIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'payment_request'),
        );
        self::assertNotEmpty($transportIssues);
    }

    /**
     * Verifie la detection de l'absence du fichier messenger.yaml.
     */
    #[Test]
    public function testDetectsMissingMessengerYaml(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=dev\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $messengerIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'messenger.yaml'),
        );
        self::assertNotEmpty($messengerIssues);
    }

    /**
     * Verifie que les problemes sont de severite WARNING et categorie DEPRECATION.
     */
    #[Test]
    public function testCreatesWarningDeprecationIssues(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=dev\n");

        $analyzer = new PaymentRequestEnvAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new PaymentRequestEnvAnalyzer();

        self::assertSame('Payment Request Env', $analyzer->getName());
    }
}
