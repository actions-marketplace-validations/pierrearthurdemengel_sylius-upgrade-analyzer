<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\SecurityFirewallAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des firewalls de sécurité dépréciés.
 * Vérifie la détection des noms "new_api_*" dans la configuration de sécurité.
 */
final class SecurityFirewallAnalyzerTest extends TestCase
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
     * Le projet trivial n'utilise pas les noms de firewalls "new_api_*".
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne true pour le projet complexe.
     * Le projet complexe utilise les anciens noms de firewalls.
     */
    #[Test]
    public function testSupportsReturnsTrueForComplexProject(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie qu'aucun problème n'est détecté pour le projet trivial.
     */
    #[Test]
    public function testNoIssuesForTrivialProject(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        self::assertCount(0, $report->getIssues());
    }

    /**
     * Vérifie la détection des noms de firewalls dépréciés dans le projet complexe.
     */
    #[Test]
    public function testDetectsDeprecatedFirewallNames(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $firewallIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Nom de firewall deprecie'),
        );

        /* 2 firewalls dépréciés : new_api_admin_user et new_api_shop_user */
        self::assertCount(2, $firewallIssues);
    }

    /**
     * Vérifie la détection des paramètres de sécurité dépréciés dans le projet complexe.
     */
    #[Test]
    public function testDetectsDeprecatedSecurityParameters(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $paramIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Parametre de securite deprecie'),
        );

        /* Multiples paramètres dépréciés dans le projet complexe */
        self::assertGreaterThan(0, count($paramIssues));
    }

    /**
     * Vérifie que le projet majeur contient plus de références dépréciées.
     */
    #[Test]
    public function testDetectsMoreIssuesInMajorProject(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();

        $reportComplex = $this->createReportForFixture('project-complex');
        $analyzer->analyze($reportComplex);
        $complexCount = count($reportComplex->getIssues());

        $reportMajor = $this->createReportForFixture('project-major');
        $analyzer->analyze($reportMajor);
        $majorCount = count($reportMajor->getIssues());

        /* Le projet majeur a au moins autant de problèmes que le projet complexe */
        self::assertGreaterThanOrEqual($complexCount, $majorCount);
    }

    /**
     * Vérifie que tous les problèmes sont de sévérité BREAKING et catégorie DEPRECATION.
     */
    #[Test]
    public function testIssuesHaveCorrectSeverityAndCategory(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Vérifie que l'estimation est de 60 minutes par référence dépréciée.
     */
    #[Test]
    public function testEstimatesSixtyMinutesPerReference(): void
    {
        $analyzer = new SecurityFirewallAnalyzer();
        $report = $this->createReportForFixture('project-complex');

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
        $analyzer = new SecurityFirewallAnalyzer();

        self::assertSame('Security Firewall', $analyzer->getName());
    }
}
