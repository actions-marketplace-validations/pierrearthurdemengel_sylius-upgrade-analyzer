<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\AutoFixInterface;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixEngine;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\MigrationFix;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour le moteur de correctifs automatiques.
 * Verifie l'orchestration des fixers, l'application des correctifs et la generation de patchs.
 */
final class FixEngineTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('fixengine_', true);
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

    /**
     * Cree un rapport de migration avec les issues specifiees.
     *
     * @param list<MigrationIssue> $issues
     */
    private function createReportWithIssues(array $issues): MigrationReport
    {
        $report = new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12',
            targetVersion: '2.0',
            projectPath: $this->tempDir,
        );

        foreach ($issues as $issue) {
            $report->addIssue($issue);
        }

        return $report;
    }

    /**
     * Cree un issue de test standard.
     */
    private function createTestIssue(string $analyzer = 'test'): MigrationIssue
    {
        return new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: $analyzer,
            message: 'Probleme de test',
            detail: 'Detail du probleme',
            suggestion: 'Suggestion de correction',
            file: 'src/Test.php',
        );
    }

    /**
     * Verifie que generateFixes retourne un tableau vide quand aucun fixer n'est enregistre.
     */
    #[Test]
    public function testGenerateFixesWithNoFixers(): void
    {
        $engine = new FixEngine([]);
        $report = $this->createReportWithIssues([$this->createTestIssue()]);

        $fixes = $engine->generateFixes($report, $this->tempDir);

        self::assertSame([], $fixes);
    }

    /**
     * Verifie que generateFixes appelle les fixers supportes.
     */
    #[Test]
    public function testGenerateFixesCallsSupportedFixers(): void
    {
        $issue = $this->createTestIssue();
        $expectedFix = new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $this->tempDir . '/src/Test.php',
            originalContent: 'ancien contenu',
            fixedContent: 'nouveau contenu',
            description: 'Correction automatique',
        );

        $fixer = $this->createMock(AutoFixInterface::class);
        $fixer->method('supports')->willReturn(true);
        $fixer->method('fix')->willReturn($expectedFix);

        $engine = new FixEngine([$fixer]);
        $report = $this->createReportWithIssues([$issue]);

        $fixes = $engine->generateFixes($report, $this->tempDir);

        self::assertCount(1, $fixes);
        self::assertSame($expectedFix, $fixes[0]);
    }

    /**
     * Verifie que applyFix ecrit le contenu corrige dans le fichier.
     */
    #[Test]
    public function testApplyFixWritesContent(): void
    {
        $filePath = $this->tempDir . '/src/Test.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, 'ancien contenu');

        $fix = new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $filePath,
            originalContent: 'ancien contenu',
            fixedContent: 'nouveau contenu',
            description: 'Correction',
        );

        $engine = new FixEngine([]);
        $engine->applyFix($fix);

        self::assertSame('nouveau contenu', file_get_contents($filePath));
    }

    /**
     * Verifie que generatePatch produit un diff unifie.
     */
    #[Test]
    public function testGeneratePatchProducesDiff(): void
    {
        $fix = new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: 'src/Test.php',
            originalContent: "ligne1\nligne2\nligne3",
            fixedContent: "ligne1\nligne_modifiee\nligne3",
            description: 'Modification de la ligne 2',
        );

        $engine = new FixEngine([]);
        $patch = $engine->generatePatch([$fix]);

        /* Le patch doit contenir les marqueurs de diff unifie */
        self::assertStringContainsString('--- a/src/Test.php', $patch);
        self::assertStringContainsString('+++ b/src/Test.php', $patch);
        self::assertStringContainsString('@@', $patch);
    }

    /**
     * Verifie qu'un fixer non supporte n'est pas appele.
     */
    #[Test]
    public function testFixerNotCalledForUnsupportedIssue(): void
    {
        $issue = $this->createTestIssue();

        $fixer = $this->createMock(AutoFixInterface::class);
        $fixer->method('supports')->willReturn(false);
        $fixer->expects(self::never())->method('fix');

        $engine = new FixEngine([$fixer]);
        $report = $this->createReportWithIssues([$issue]);

        $fixes = $engine->generateFixes($report, $this->tempDir);

        self::assertSame([], $fixes);
    }

    /**
     * Verifie que plusieurs fixers peuvent traiter la meme issue.
     */
    #[Test]
    public function testMultipleFixersCanHandleSameIssue(): void
    {
        $issue = $this->createTestIssue();

        $fix1 = new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: 'file1.php',
            originalContent: 'a',
            fixedContent: 'b',
            description: 'Fix 1',
        );
        $fix2 = new MigrationFix(
            confidence: FixConfidence::MEDIUM,
            filePath: 'file2.php',
            originalContent: 'c',
            fixedContent: 'd',
            description: 'Fix 2',
        );

        $fixer1 = $this->createMock(AutoFixInterface::class);
        $fixer1->method('supports')->willReturn(true);
        $fixer1->method('fix')->willReturn($fix1);

        $fixer2 = $this->createMock(AutoFixInterface::class);
        $fixer2->method('supports')->willReturn(true);
        $fixer2->method('fix')->willReturn($fix2);

        $engine = new FixEngine([$fixer1, $fixer2]);
        $report = $this->createReportWithIssues([$issue]);

        $fixes = $engine->generateFixes($report, $this->tempDir);

        self::assertCount(2, $fixes);
    }

    /**
     * Verifie que generateFixes ignore les resultats null des fixers.
     */
    #[Test]
    public function testGenerateFixesSkipsNullResults(): void
    {
        $issue = $this->createTestIssue();

        $fixer = $this->createMock(AutoFixInterface::class);
        $fixer->method('supports')->willReturn(true);
        $fixer->method('fix')->willReturn(null);

        $engine = new FixEngine([$fixer]);
        $report = $this->createReportWithIssues([$issue]);

        $fixes = $engine->generateFixes($report, $this->tempDir);

        self::assertSame([], $fixes);
    }

    /**
     * Verifie que applyFix cree le repertoire parent si necessaire.
     */
    #[Test]
    public function testApplyFixCreatesDirectory(): void
    {
        $filePath = $this->tempDir . '/new/deep/directory/Test.php';

        $fix = new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $filePath,
            originalContent: '',
            fixedContent: '<?php // nouveau fichier',
            description: 'Creation de fichier',
        );

        $engine = new FixEngine([]);
        $engine->applyFix($fix);

        self::assertFileExists($filePath);
        self::assertSame('<?php // nouveau fichier', file_get_contents($filePath));
    }
}
