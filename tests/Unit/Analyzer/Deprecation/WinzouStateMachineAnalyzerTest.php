<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\WinzouStateMachineAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de winzou/state-machine-bundle.
 * Vérifie la détection des machines à états et l'estimation de l'effort de migration.
 */
final class WinzouStateMachineAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne true pour le projet modéré qui contient winzou.
     * Le composer.json du projet modéré déclare winzou/state-machine-bundle.
     */
    #[Test]
    public function testSupportsReturnsTrueForProjectWithWinzou(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne false pour le projet trivial qui ne contient pas winzou.
     * Le composer.json du projet trivial ne déclare pas winzou/state-machine-bundle.
     */
    #[Test]
    public function testSupportsReturnsFalseForProjectWithoutWinzou(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Vérifie la détection d'une seule machine à états dans le projet modéré.
     * Le fichier winzou_state_machine.yaml du projet modéré définit sylius_order_checkout.
     */
    #[Test]
    public function testDetectsOneStateMachineInModerateProject(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche des problèmes mentionnant une machine à états spécifique */
        $smIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Machine a etats winzou'),
        );

        /* Le projet modéré définit exactement 1 machine à états */
        self::assertCount(1, $smIssues);
    }

    /**
     * Vérifie la détection de trois machines à états dans le projet complexe.
     * Le fichier winzou_state_machine.yaml du projet complexe définit 3 machines.
     */
    #[Test]
    public function testDetectsThreeStateMachinesInComplexProject(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problèmes mentionnant une machine à états spécifique */
        $smIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Machine a etats winzou'),
        );

        /* Le projet complexe définit 3 machines à états : checkout, payment, shipping */
        self::assertCount(3, $smIssues);
    }

    /**
     * Vérifie que tous les problèmes créés par l'analyseur sont de sévérité BREAKING.
     * La migration de winzou vers Symfony Workflow est un changement cassant.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Tous les problèmes détectés doivent être de sévérité BREAKING */
        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
        }
    }

    /**
     * Vérifie l'estimation de 4 heures (240 minutes) par machine à états.
     * Le problème global du projet modéré (1 SM) doit estimer 240 minutes.
     */
    #[Test]
    public function testEstimatesFourHoursPerStateMachine(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche du problème global résumant l'estimation */
        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'machine(s) a etats winzou detectee(s)'),
        ));

        self::assertNotEmpty($globalIssues, 'Le problème global devrait être présent.');

        /* 1 machine à états × 240 minutes = 240 minutes */
        self::assertSame(240, $globalIssues[0]->getEstimatedMinutes());
    }

    /**
     * Vérifie l'estimation globale pour le projet complexe avec 3 machines à états.
     * 3 machines × 240 minutes = 720 minutes.
     */
    #[Test]
    public function testEstimatesCorrectTotalForMultipleStateMachines(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème global résumant l'estimation */
        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'machine(s) a etats winzou detectee(s)'),
        ));

        self::assertNotEmpty($globalIssues, 'Le problème global devrait être présent.');

        /* 3 machines à états × 240 minutes = 720 minutes */
        self::assertSame(720, $globalIssues[0]->getEstimatedMinutes());
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new WinzouStateMachineAnalyzer();

        self::assertSame('Winzou State Machine', $analyzer->getName());
    }
}
