<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\RemovedClassAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de classes supprimées dans Sylius 2.0.
 * Vérifie la détection des instructions use référençant des classes supprimées.
 */
final class RemovedClassAnalyzerTest extends TestCase
{
    /** Chemin vers le répertoire des fixtures */
    private const FIXTURES_PATH = __DIR__ . '/../../../Fixtures';

    /**
     * Crée un rapport de migration pointant vers le projet de fixture spécifié.
     */
    private function createReportForFixture(string $projectName): MigrationReport
    {
        $path = realpath(self::FIXTURES_PATH . '/' . $projectName);
        self::assertNotFalse($path, sprintf('Le répertoire de fixture "%s" est introuvable.', $projectName));

        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: null,
            targetVersion: '2.2',
            projectPath: $path,
        );
    }

    /**
     * Vérifie que supports retourne true quand src/ existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWhenSrcDirExists(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie qu'aucune classe supprimée n'est détectée dans le projet trivial.
     * Le projet trivial ne contient pas de fichiers PHP utilisant des classes supprimées.
     */
    #[Test]
    public function testNoIssuesForTrivialProject(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        /* Filtrer uniquement les problèmes de cet analyseur */
        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        self::assertCount(0, $removedClassIssues);
    }

    /**
     * Vérifie la détection d'une classe supprimée dans le projet modéré.
     * Le projet modéré utilise CurrencyBundle\Templating\Helper\CurrencyHelper.
     */
    #[Test]
    public function testDetectsRemovedClassInModerateProject(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        /* Au moins 1 classe supprimée : CurrencyHelper */
        self::assertGreaterThanOrEqual(1, count($removedClassIssues));
    }

    /**
     * Vérifie la détection de classes du Dashboard dans le projet complexe.
     * Le projet complexe utilise DashboardStatistics, StatisticsController et StatisticsDataProvider.
     */
    #[Test]
    public function testDetectsDashboardClassesInComplexProject(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $dashboardIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class'
                && (str_contains($issue->getMessage(), 'Dashboard')
                    || str_contains($issue->getMessage(), 'Statistics')),
        );

        /* 3 classes : DashboardStatistics, StatisticsController, StatisticsDataProvider */
        self::assertCount(3, $dashboardIssues);
    }

    /**
     * Vérifie la détection de nombreuses classes supprimées dans le projet majeur.
     * Le projet majeur utilise de nombreuses classes de UiBundle, ProductBundle, etc.
     */
    #[Test]
    public function testDetectsManyRemovedClassesInMajorProject(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        /* Le projet majeur a de nombreuses classes supprimées dans 3 fichiers */
        self::assertGreaterThan(10, count($removedClassIssues));
    }

    /**
     * Vérifie que tous les problèmes sont de sévérité BREAKING et catégorie DEPRECATION.
     */
    #[Test]
    public function testIssuesHaveCorrectSeverityAndCategory(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        foreach ($removedClassIssues as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Vérifie que l'estimation est de 60 minutes par usage de classe supprimée.
     */
    #[Test]
    public function testEstimatesSixtyMinutesPerUsage(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        foreach ($removedClassIssues as $issue) {
            self::assertSame(60, $issue->getEstimatedMinutes());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new RemovedClassAnalyzer();

        self::assertSame('Removed Class', $analyzer->getName());
    }

    /**
     * Vérifie que les numéros de lignes sont correctement rapportés.
     */
    #[Test]
    public function testReportsCorrectLineNumbers(): void
    {
        $analyzer = new RemovedClassAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $removedClassIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => $issue->getAnalyzer() === 'Removed Class',
        );

        foreach ($removedClassIssues as $issue) {
            self::assertNotNull($issue->getLine(), 'Le numéro de ligne devrait être renseigné.');
            self::assertGreaterThan(0, $issue->getLine());
        }
    }
}
