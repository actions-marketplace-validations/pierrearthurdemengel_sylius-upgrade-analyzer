<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Grid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Grid\GridCustomizationAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de personnalisations de grilles Sylius.
 * Verifie la detection des definitions YAML, des classes PHP et l'estimation du temps.
 */
final class GridCustomizationAnalyzerTest extends TestCase
{
    /** Chemin vers le repertoire des fixtures */
    private const FIXTURES_PATH = __DIR__ . '/../../../Fixtures';

    /**
     * Cree un rapport de migration pointant vers le projet de fixture specifie.
     */
    private function createReportForFixture(string $projectName): MigrationReport
    {
        /* Resolution du chemin reel pour eviter les problemes de chemins relatifs */
        $path = realpath(self::FIXTURES_PATH . '/' . $projectName);
        self::assertNotFalse($path, sprintf('Le repertoire de fixture "%s" est introuvable.', $projectName));

        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: null,
            targetVersion: '2.2',
            projectPath: $path,
        );
    }

    /**
     * Verifie que supports() retourne true pour un projet avec sylius_grid.yaml.
     * Le projet complexe contient un fichier config/packages/sylius_grid.yaml.
     */
    #[Test]
    public function testSupportsReturnsTrueWithGridConfig(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        /* Le projet complexe contient sylius_grid.yaml, supports doit retourner true */
        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que supports() retourne false pour un projet sans configuration de grilles.
     * Le projet trivial ne contient aucun fichier sylius_grid.
     */
    #[Test]
    public function testSupportsReturnsFalseWithNoGridConfig(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        /* Le projet trivial n'a ni YAML de grilles ni classes PHP de grilles */
        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte les definitions de grilles depuis le fichier YAML.
     * Le fichier sylius_grid.yaml du projet complexe contient une grille app_admin_order.
     */
    #[Test]
    public function testDetectsGridDefinitionsFromYaml(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problemes mentionnant les definitions YAML de grilles */
        $yamlIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'definition(s) de grille(s) YAML'),
        );

        self::assertNotEmpty($yamlIssues, 'Les definitions de grilles YAML auraient du etre detectees.');

        /* Verification que la grille app_admin_order est mentionnee dans le detail */
        $yamlIssue = array_values($yamlIssues)[0];
        self::assertStringContainsString('app_admin_order', $yamlIssue->getDetail());
    }

    /**
     * Verifie que les problemes de grilles sont de severite WARNING.
     * Toutes les detections de grilles doivent etre classees comme avertissements.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Tous les problemes detectes doivent etre des WARNING */
        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
            self::assertSame(Category::GRID, $issue->getCategory());
        }
    }

    /**
     * Verifie l'estimation en heures par grille YAML.
     * Chaque grille simple YAML est estimee a 60 minutes (1 heure).
     * Le projet complexe contient 1 grille, donc 60 minutes au total.
     */
    #[Test]
    public function testEstimatesCorrectHoursPerGrid(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche de l'issue YAML pour verifier l'estimation */
        $yamlIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'definition(s) de grille(s) YAML'),
        ));

        self::assertNotEmpty($yamlIssues);

        /* 1 grille * 60 minutes = 60 minutes */
        self::assertSame(60, $yamlIssues[0]->getEstimatedMinutes());
    }

    /**
     * Verifie que getName() retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new GridCustomizationAnalyzer();

        self::assertSame('Grid Customization', $analyzer->getName());
    }

    /**
     * Verifie que l'analyseur attribue bien le nom 'Grid Customization' aux issues.
     * Le champ analyzer de chaque issue doit correspondre au nom de l'analyseur.
     */
    #[Test]
    public function testIssuesReferenceCorrectAnalyzerName(): void
    {
        $analyzer = new GridCustomizationAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Chaque issue doit mentionner le bon nom d'analyseur */
        foreach ($report->getIssues() as $issue) {
            self::assertSame('Grid Customization', $issue->getAnalyzer());
        }
    }
}
