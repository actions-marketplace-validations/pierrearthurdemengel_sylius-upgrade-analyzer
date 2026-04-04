<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\PayumAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de Payum.
 * Vérifie la détection des dépendances, configurations et usages de Payum.
 */
final class PayumAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne true pour le projet complexe qui contient Payum.
     * Le composer.json du projet complexe déclare payum/core et payum/payum-bundle.
     */
    #[Test]
    public function testSupportsReturnsTrueForProjectWithPayum(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne false pour le projet trivial sans Payum.
     * Le composer.json du projet trivial ne déclare aucun paquet Payum.
     */
    #[Test]
    public function testSupportsReturnsFalseForProjectWithoutPayum(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Vérifie la détection des dépendances Payum dans composer.json.
     * Le projet complexe déclare payum/core et payum/payum-bundle.
     */
    #[Test]
    public function testDetectsPayumDependency(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problèmes mentionnant les dépendances Payum */
        $composerIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Dependance')
                && str_contains($issue->getMessage(), 'detectee dans composer.json'),
        );

        /* Le projet complexe déclare payum/core et payum/payum-bundle */
        self::assertCount(2, $composerIssues);
    }

    /**
     * Vérifie la détection des gateways Payum dans la configuration YAML.
     * Le projet complexe définit 2 gateways : offline et stripe_checkout.
     */
    #[Test]
    public function testDetectsPayumGatewayConfig(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problèmes mentionnant les gateways */
        $gatewayIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Gateway Payum'),
        );

        /* Le fichier payum.yaml définit 2 gateways : offline et stripe_checkout */
        self::assertCount(2, $gatewayIssues);
    }

    /**
     * Vérifie que tous les problèmes créés sont de sévérité BREAKING.
     * La migration depuis Payum est un changement cassant.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Tous les problèmes détectés doivent être de sévérité BREAKING */
        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new PayumAnalyzer();

        self::assertSame('Payum', $analyzer->getName());
    }

    /**
     * Vérifie que le problème global contient l'estimation de migration.
     * Le projet complexe a 2 gateways standard et 0 gateways personnalisées.
     */
    #[Test]
    public function testGlobalIssueContainsMigrationEstimate(): void
    {
        $analyzer = new PayumAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème global résumant la migration */
        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Migration Payum requise'),
        ));

        self::assertNotEmpty($globalIssues, 'Le problème global devrait être présent.');

        /* 2 gateways standard × 120 minutes = 240 minutes */
        self::assertSame(240, $globalIssues[0]->getEstimatedMinutes());
    }
}
