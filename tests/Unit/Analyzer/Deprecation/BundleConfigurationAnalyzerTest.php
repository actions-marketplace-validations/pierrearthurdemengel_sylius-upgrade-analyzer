<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\BundleConfigurationAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de configuration des bundles.
 * Vérifie la détection des bundles obsolètes et manquants dans config/bundles.php.
 */
final class BundleConfigurationAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne true quand config/bundles.php existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWhenBundlesPhpExists(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie qu'aucun bundle obsolète n'est détecté dans le projet trivial.
     * Le projet trivial a une configuration bundles.php propre.
     */
    #[Test]
    public function testNoObsoleteBundlesInTrivialProject(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        /* Recherche des problèmes de bundles obsolètes */
        $obsoleteIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'obsolete'),
        );

        self::assertCount(0, $obsoleteIssues);
    }

    /**
     * Vérifie qu'aucun bundle manquant n'est détecté dans le projet trivial.
     * Le projet trivial contient tous les bundles requis.
     */
    #[Test]
    public function testNoMissingBundlesInTrivialProject(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        /* Recherche des problèmes de bundles manquants */
        $missingIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'manquant'),
        );

        self::assertCount(0, $missingIssues);
    }

    /**
     * Vérifie la détection des bundles obsolètes dans le projet modéré.
     * Le projet modéré contient winzouStateMachineBundle et SyliusCalendarBundle.
     */
    #[Test]
    public function testDetectsObsoleteBundlesInModerateProject(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $obsoleteIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'obsolete'),
        );

        /* Le projet modéré a 2 bundles obsolètes : winzou et Calendar */
        self::assertCount(2, $obsoleteIssues);
    }

    /**
     * Vérifie la détection de tous les bundles obsolètes dans le projet complexe.
     * Le projet complexe contient les 7 bundles obsolètes.
     */
    #[Test]
    public function testDetectsAllObsoleteBundlesInComplexProject(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $obsoleteIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'obsolete'),
        );

        /* Le projet complexe a 7 bundles obsolètes */
        self::assertCount(7, $obsoleteIssues);
    }

    /**
     * Vérifie la détection des bundles manquants dans le projet complexe.
     * Le projet complexe ne contient aucun des bundles requis sauf l'ancien ApiPlatform.
     */
    #[Test]
    public function testDetectsMissingBundlesInComplexProject(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $missingIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'manquant'),
        );

        /* Le projet complexe n'a aucun des 6 bundles requis */
        self::assertCount(6, $missingIssues);
    }

    /**
     * Vérifie que tous les problèmes sont de sévérité BREAKING et catégorie DEPRECATION.
     */
    #[Test]
    public function testIssuesHaveCorrectSeverityAndCategory(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Vérifie que l'estimation est de 30 minutes par problème de bundle.
     */
    #[Test]
    public function testEstimatesThirtyMinutesPerBundle(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(30, $issue->getEstimatedMinutes());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new BundleConfigurationAnalyzer();

        self::assertSame('Bundle Configuration', $analyzer->getName());
    }
}
