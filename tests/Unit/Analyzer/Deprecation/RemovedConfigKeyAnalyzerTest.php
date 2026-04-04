<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\RemovedConfigKeyAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des cles de configuration supprimees.
 * Verifie la detection des cles obsoletes dans les fichiers YAML de config/.
 */
final class RemovedConfigKeyAnalyzerTest extends TestCase
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
     * Verifie que supports retourne false pour le projet trivial sans cles supprimees.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour le projet moderate avec des cles supprimees.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte autoconfigure_with_attributes dans le projet moderate.
     */
    #[Test]
    public function testDetectsAutoconfigureWithAttributes(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $issues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'autoconfigure_with_attributes'),
        );

        self::assertNotEmpty($issues);
    }

    /**
     * Verifie que le projet major detecte plus de problemes que le projet moderate.
     */
    #[Test]
    public function testMajorProjectHasMoreIssuesThanModerate(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();

        $moderateReport = $this->createReportForFixture('project-moderate');
        $analyzer->analyze($moderateReport);
        $moderateCount = count($moderateReport->getIssues());

        $majorReport = $this->createReportForFixture('project-major');
        $analyzer->analyze($majorReport);
        $majorCount = count($majorReport->getIssues());

        self::assertGreaterThan($moderateCount, $majorCount);
    }

    /**
     * Verifie que l'analyseur detecte state_machine et d'autres cles dans le projet complex.
     */
    #[Test]
    public function testDetectsMultipleRemovedKeysInComplex(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $stateMachineIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'state_machine'),
        );

        self::assertNotEmpty($stateMachineIssues);
    }

    /**
     * Verifie que tous les problemes sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testCreatesBreakingDeprecationIssues(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
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
        $analyzer = new RemovedConfigKeyAnalyzer();

        self::assertSame('Removed Config Key', $analyzer->getName());
    }

    /**
     * Verifie que l'estimation est de 30 minutes par cle de configuration.
     */
    #[Test]
    public function testEstimatesThirtyMinutesPerConfigKey(): void
    {
        $analyzer = new RemovedConfigKeyAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'cle(s) de configuration supprimee(s)'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de synthese devrait etre present.');
        /* Au moins 1 cle x 30 minutes */
        self::assertGreaterThanOrEqual(30, $globalIssues[0]->getEstimatedMinutes());
    }
}
