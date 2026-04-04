<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\CalendarClockAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de migration sylius/calendar vers symfony/clock.
 * Vérifie la détection de la dépendance et des usages de DateTimeProviderInterface.
 */
final class CalendarClockAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne false pour le projet trivial.
     * Le projet trivial n'utilise pas sylius/calendar.
     */
    #[Test]
    public function testSupportsReturnsFalseForProjectWithoutCalendar(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne true pour le projet modéré.
     * Le projet modéré déclare sylius/calendar dans composer.json.
     */
    #[Test]
    public function testSupportsReturnsTrueForProjectWithCalendar(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie qu'aucun problème n'est détecté pour le projet trivial.
     */
    #[Test]
    public function testNoIssuesForTrivialProject(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        self::assertCount(0, $report->getIssues());
    }

    /**
     * Vérifie la détection de la dépendance composer dans le projet modéré.
     */
    #[Test]
    public function testDetectsComposerDependency(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $composerIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'composer.json'),
        );

        self::assertCount(1, $composerIssues);
    }

    /**
     * Vérifie la détection des usages de DateTimeProviderInterface dans le projet modéré.
     * Le projet modéré a 1 fichier avec 1 use statement.
     */
    #[Test]
    public function testDetectsUsageInModerateProject(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        $usageIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'DateTimeProviderInterface'),
        );

        /* 1 fichier avec 1 référence */
        self::assertCount(1, $usageIssues);
    }

    /**
     * Vérifie la détection de multiples usages dans le projet complexe.
     * Le projet complexe a 2 fichiers utilisant DateTimeProviderInterface.
     */
    #[Test]
    public function testDetectsMultipleUsagesInComplexProject(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $usageIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'DateTimeProviderInterface'),
        );

        /* 2 fichiers : PromotionChecker (use statement) et ExpirationChecker (FQCN) */
        self::assertCount(2, $usageIssues);
    }

    /**
     * Vérifie que tous les problèmes sont de sévérité BREAKING et catégorie DEPRECATION.
     */
    #[Test]
    public function testIssuesHaveCorrectSeverityAndCategory(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Vérifie que l'estimation est de 60 minutes par usage.
     */
    #[Test]
    public function testEstimatesSixtyMinutesPerUsage(): void
    {
        $analyzer = new CalendarClockAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(60, $issue->getEstimatedMinutes());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new CalendarClockAnalyzer();

        self::assertSame('Calendar to Clock', $analyzer->getName());
    }
}
