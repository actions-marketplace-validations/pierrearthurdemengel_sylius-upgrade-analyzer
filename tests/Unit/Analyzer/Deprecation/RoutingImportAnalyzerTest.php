<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\RoutingImportAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des imports de routing obsoletes.
 * Verifie la detection des anciens chemins d'import de routes et du parametre d'API.
 */
final class RoutingImportAnalyzerTest extends TestCase
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
     * Verifie que supports retourne false pour le projet trivial sans routing obsolete.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour le projet moderate avec un import obsolete.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte l'ancien import payum dans le projet moderate.
     */
    #[Test]
    public function testDetectsOldPayumImport(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $payumIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'payum'),
        );

        self::assertNotEmpty($payumIssues);
    }

    /**
     * Verifie que l'analyseur detecte le parametre %sylius.security.new_api_route% dans le projet complex.
     */
    #[Test]
    public function testDetectsOldApiRouteParam(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $apiRouteIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'new_api_route'),
        );

        self::assertNotEmpty($apiRouteIssues);
    }

    /**
     * Verifie que le projet major detecte plus de problemes que le projet moderate.
     */
    #[Test]
    public function testMajorProjectHasMoreIssuesThanModerate(): void
    {
        $analyzer = new RoutingImportAnalyzer();

        $moderateReport = $this->createReportForFixture('project-moderate');
        $analyzer->analyze($moderateReport);
        $moderateCount = count($moderateReport->getIssues());

        $majorReport = $this->createReportForFixture('project-major');
        $analyzer->analyze($majorReport);
        $majorCount = count($majorReport->getIssues());

        self::assertGreaterThan($moderateCount, $majorCount);
    }

    /**
     * Verifie que tous les problemes sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testCreatesBreakingDeprecationIssues(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-complex');

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
        $analyzer = new RoutingImportAnalyzer();

        self::assertSame('Routing Import', $analyzer->getName());
    }

    /**
     * Verifie que l'estimation est de 30 minutes par probleme de routing.
     */
    #[Test]
    public function testEstimatesThirtyMinutesPerRoutingIssue(): void
    {
        $analyzer = new RoutingImportAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'import(s) de routing obsolete(s)'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de synthese devrait etre present.');
        /* Au moins 1 import x 30 minutes */
        self::assertGreaterThanOrEqual(30, $globalIssues[0]->getEstimatedMinutes());
    }
}
