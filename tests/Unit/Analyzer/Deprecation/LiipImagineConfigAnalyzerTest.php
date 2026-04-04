<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\LiipImagineConfigAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de la configuration Liip Imagine.
 * Verifie la detection des resolvers/loaders "default" et du filtre resolve_cache_relative.
 */
final class LiipImagineConfigAnalyzerTest extends TestCase
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
     * Verifie que supports retourne false pour le projet trivial sans configuration liip_imagine.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour le projet moderate avec liip_imagine.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte le resolver "default" dans le projet moderate.
     */
    #[Test]
    public function testDetectsDefaultResolver(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $resolverIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Resolver "default"'),
        );

        self::assertNotEmpty($resolverIssues);
    }

    /**
     * Verifie que l'analyseur detecte le loader "default" dans le projet complex.
     */
    #[Test]
    public function testDetectsDefaultLoader(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $loaderIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Loader "default"'),
        );

        self::assertNotEmpty($loaderIssues);
    }

    /**
     * Verifie que l'analyseur detecte resolve_cache_relative dans le projet major.
     */
    #[Test]
    public function testDetectsResolveCacheRelative(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        $cacheIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'resolve_cache_relative'),
        );

        self::assertNotEmpty($cacheIssues);
    }

    /**
     * Verifie que le projet major genere plus de problemes que le projet moderate.
     */
    #[Test]
    public function testMajorProjectHasMoreIssues(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();

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
        $analyzer = new LiipImagineConfigAnalyzer();
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
        $analyzer = new LiipImagineConfigAnalyzer();

        self::assertSame('Liip Imagine Config', $analyzer->getName());
    }

    /**
     * Verifie que l'estimation est de 60 minutes par configuration.
     */
    #[Test]
    public function testEstimatesSixtyMinutesPerConfig(): void
    {
        $analyzer = new LiipImagineConfigAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'probleme(s) de configuration Liip Imagine'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de synthese devrait etre present.');
        /* Au moins 1 configuration x 60 minutes */
        self::assertGreaterThanOrEqual(60, $globalIssues[0]->getEstimatedMinutes());
    }
}
