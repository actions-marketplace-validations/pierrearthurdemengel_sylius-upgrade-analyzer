<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\TranslationKeyAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des cles de traduction.
 * Verifie la detection des cles de traduction Sylius renommees dans Sylius 2.0.
 */
final class TranslationKeyAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('transkey_', true);
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
     * Verifie que supports retourne false sans le repertoire translations/.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutTranslationsDir(): void
    {
        $analyzer = new TranslationKeyAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand translations/ existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWithTranslationsDir(): void
    {
        mkdir($this->tempDir . '/translations', 0755, true);

        $analyzer = new TranslationKeyAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection des cles de traduction avec prefixe renomme.
     */
    #[Test]
    public function testDetectsChangedTranslationKeyPrefixes(): void
    {
        $transDir = $this->tempDir . '/translations';
        mkdir($transDir, 0755, true);
        file_put_contents($transDir . '/messages.fr.yaml', <<<'YAML'
sylius:
    ui:
        admin:
            dashboard: Tableau de bord
            orders: Commandes
YAML);

        $analyzer = new TranslationKeyAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $transIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'sylius.ui.admin'),
        );
        self::assertNotEmpty($transIssues);
    }

    /**
     * Verifie que les problemes sont de severite SUGGESTION.
     */
    #[Test]
    public function testCreatesSuggestionIssues(): void
    {
        $transDir = $this->tempDir . '/translations';
        mkdir($transDir, 0755, true);
        file_put_contents($transDir . '/messages.fr.yaml', <<<'YAML'
sylius:
    ui:
        admin:
            dashboard: Tableau de bord
YAML);

        $analyzer = new TranslationKeyAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::SUGGESTION, $issue->getSeverity());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new TranslationKeyAnalyzer();

        self::assertSame('Translation Key', $analyzer->getName());
    }
}
