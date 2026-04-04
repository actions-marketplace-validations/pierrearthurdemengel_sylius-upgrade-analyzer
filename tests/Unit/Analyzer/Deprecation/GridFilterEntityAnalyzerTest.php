<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\GridFilterEntityAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des filtres de grille entity/entities.
 * Verifie la detection du type `entities` obsolete et de l'option `field:` au singulier
 * dans les fichiers YAML de configuration.
 */
final class GridFilterEntityAnalyzerTest extends TestCase
{
    /** Chemin vers le repertoire des fixtures */
    private const FIXTURES_PATH = __DIR__ . '/../../../Fixtures';

    /**
     * Cree un rapport de migration pointant vers le projet de fixture specifie.
     */
    private function createReportForFixture(string $projectName): MigrationReport
    {
        $path = realpath(self::FIXTURES_PATH . '/' . $projectName);
        self::assertNotFalse($path, sprintf('Le repertoire de fixture "%s" est introuvable.', $projectName));

        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: null,
            targetVersion: '2.0',
            projectPath: $path,
        );
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();

        self::assertSame('Grid Filter Entity', $analyzer->getName());
    }

    /**
     * Verifie que supports retourne false pour un projet sans filtres entities.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour un projet avec des filtres entities.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection du type `entities` obsolete.
     */
    #[Test]
    public function testDetectsEntitiesType(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant type: entities */
        $entitiesIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '`entities`'),
        );

        self::assertNotEmpty($entitiesIssues, 'Le type de filtre `entities` aurait du etre detecte.');
    }

    /**
     * Verifie la detection de l'option `field:` au singulier.
     */
    #[Test]
    public function testDetectsFieldSingular(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant field: au singulier */
        $fieldIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), '`field:`'),
        );

        self::assertNotEmpty($fieldIssues, 'L\'option `field:` au singulier aurait du etre detectee.');
    }

    /**
     * Verifie que toutes les issues sont de severite BREAKING et categorie GRID.
     */
    #[Test]
    public function testAllIssuesAreBreakingInGridCategory(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::GRID, $issue->getCategory());
        }
    }

    /**
     * Verifie que le projet majeur detecte plus de filtres que le projet modere.
     */
    #[Test]
    public function testMajorProjectDetectsMoreFilters(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();

        $moderateReport = $this->createReportForFixture('project-moderate');
        $analyzer->analyze($moderateReport);
        $moderateCount = count($moderateReport->getIssues());

        $majorReport = $this->createReportForFixture('project-major');
        $analyzer->analyze($majorReport);
        $majorCount = count($majorReport->getIssues());

        self::assertGreaterThan(
            $moderateCount,
            $majorCount,
            'Le projet majeur devrait detecter plus de filtres que le projet modere.',
        );
    }

    /**
     * Verifie l'estimation du temps (30 min par filtre).
     */
    #[Test]
    public function testEstimatesCorrectMinutesPerFilter(): void
    {
        $analyzer = new GridFilterEntityAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche de l'issue de synthese */
        $summaryIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'filtre(s) de grille'),
        ));

        self::assertNotEmpty($summaryIssues);

        /* L'estimation doit etre un multiple de 30 */
        $minutes = $summaryIssues[0]->getEstimatedMinutes();
        self::assertSame(0, $minutes % 30, 'L\'estimation doit etre un multiple de 30 minutes.');
        self::assertGreaterThan(0, $minutes);
    }
}
