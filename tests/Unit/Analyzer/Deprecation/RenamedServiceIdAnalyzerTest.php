<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\RenamedServiceIdAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des identifiants de services renommes.
 * Verifie la detection des anciens identifiants de services Sylius dans les fichiers YAML.
 */
final class RenamedServiceIdAnalyzerTest extends TestCase
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
     * Verifie que supports retourne false pour le projet trivial sans anciens services.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour le projet moderate contenant des anciens services.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte les services renommes dans le projet moderate.
     * Le projet moderate contient 2 references : sylius.dashboard.statistics_provider et sylius.twig.extension.sort_by.
     */
    #[Test]
    public function testDetectsRenamedServicesInModerateProject(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche des problemes specifiques aux services */
        $serviceIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Identifiant de service obsolete'),
        );

        self::assertGreaterThanOrEqual(2, count($serviceIssues));
    }

    /**
     * Verifie que l'analyseur detecte davantage de services dans le projet major.
     */
    #[Test]
    public function testDetectsMoreServicesInMajorProject(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        $serviceIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Identifiant de service obsolete'),
        );

        /* Le projet major contient de nombreuses references aux anciens services */
        self::assertGreaterThanOrEqual(10, count($serviceIssues));
    }

    /**
     * Verifie que tous les problemes detectes sont de severite BREAKING.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
        }
    }

    /**
     * Verifie que le probleme global contient l'estimation correcte.
     * Chaque reference = 30 minutes.
     */
    #[Test]
    public function testEstimatesThirtyMinutesPerService(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'reference(s) a des identifiants de services'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de synthese devrait etre present.');
        /* Au moins 2 references x 30 minutes = 60 minutes minimum */
        self::assertGreaterThanOrEqual(60, $globalIssues[0]->getEstimatedMinutes());
    }

    /**
     * Verifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();

        self::assertSame('Renamed Service ID', $analyzer->getName());
    }

    /**
     * Verifie que la categorie est DEPRECATION.
     */
    #[Test]
    public function testCategoryIsDeprecation(): void
    {
        $analyzer = new RenamedServiceIdAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }
}
