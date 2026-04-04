<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\MessageBusRenameAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de renommage des bus de messages.
 * Verifie la detection des references aux anciens noms de bus Sylius.
 */
final class MessageBusRenameAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('msgbus_', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Supprime recursivement un repertoire temporaire.
     */
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

    /**
     * Cree un rapport de migration pointant vers le repertoire temporaire.
     */
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
     * Verifie que supports retourne false pour un projet vide sans src/ ni config/.
     */
    #[Test]
    public function testSupportsReturnsFalseForEmptyProject(): void
    {
        $analyzer = new MessageBusRenameAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte les references a sylius_default.bus dans un YAML.
     */
    #[Test]
    public function testDetectsBusReferences(): void
    {
        /* Creation d'un fichier de configuration contenant l'ancien nom de bus */
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', <<<'YAML'
framework:
    messenger:
        buses:
            sylius_default.bus:
                middleware:
                    - doctrine_transaction
YAML);

        $analyzer = new MessageBusRenameAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));

        $analyzer->analyze($report);

        /* Verification qu'au moins un probleme a ete detecte */
        self::assertNotEmpty($report->getIssues());

        /* Verification qu'un probleme mentionne sylius_default.bus */
        $busIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'sylius_default.bus'),
        );
        self::assertNotEmpty($busIssues);
    }

    /**
     * Verifie que les problemes crees sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', <<<'YAML'
framework:
    messenger:
        buses:
            sylius_default.bus: ~
YAML);

        $analyzer = new MessageBusRenameAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
        }
    }

    /**
     * Verifie l'estimation de 60 minutes par reference.
     * Le resume global doit avoir estimatedMinutes = nombre_de_references * 60.
     */
    #[Test]
    public function testEstimatesOneHourPerReference(): void
    {
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/messenger.yaml', <<<'YAML'
framework:
    messenger:
        buses:
            sylius_default.bus: ~
            sylius_event.bus: ~
YAML);

        $analyzer = new MessageBusRenameAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        /* Recherche du probleme global de resume */
        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'reference(s) aux anciens noms de bus'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de resume devrait etre present.');

        /* Chaque reference = 60 minutes, 2 references = 120 minutes */
        self::assertSame(120, $globalIssues[0]->getEstimatedMinutes());
    }

    /**
     * Verifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new MessageBusRenameAnalyzer();

        self::assertSame('Message Bus Rename', $analyzer->getName());
    }
}
