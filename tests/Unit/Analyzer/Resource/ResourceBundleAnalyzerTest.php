<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Resource\ResourceBundleAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur du systeme de ressources Sylius.
 * Verifie la detection des definitions YAML, des repositories et factories.
 */
final class ResourceBundleAnalyzerTest extends TestCase
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
     * Verifie que supports() retourne true pour un projet avec sylius_resource.yaml.
     * Le projet complexe contient un fichier config/packages/sylius_resource.yaml.
     */
    #[Test]
    public function testSupportsReturnsTrueWithResourceConfig(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        /* Le projet complexe contient sylius_resource.yaml, supports doit retourner true */
        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que supports() retourne false pour un projet sans configuration de ressources.
     * Le projet trivial ne contient aucun fichier sylius_resource.
     */
    #[Test]
    public function testSupportsReturnsFalseWithNoResourceConfig(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        /* Le projet trivial n'a ni YAML de ressources ni classes PHP de ressources */
        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte les definitions de ressources depuis le fichier YAML.
     * Le fichier sylius_resource.yaml du projet complexe contient la ressource app.product.
     */
    #[Test]
    public function testDetectsResourceDefinitionsFromYaml(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problemes mentionnant les definitions YAML de ressources */
        $yamlIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'definition(s) de ressource(s) YAML'),
        );

        self::assertNotEmpty($yamlIssues, 'Les definitions de ressources YAML auraient du etre detectees.');

        /* Verification que la ressource app.product est mentionnee dans le detail */
        $yamlIssue = array_values($yamlIssues)[0];
        self::assertStringContainsString('app.product', $yamlIssue->getDetail());
    }

    /**
     * Verifie que l'analyseur detecte le repository personnalise ProductRepository.
     * Le projet complexe definit un ProductRepository dans sylius_resource.yaml
     * et possede la classe PHP correspondante.
     */
    #[Test]
    public function testDetectsCustomRepositories(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche des problemes mentionnant le detail du repository personnalise */
        $yamlIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'definition(s) de ressource(s) YAML'),
        ));

        self::assertNotEmpty($yamlIssues);

        /* Le detail YAML doit mentionner les repositories personnalises */
        self::assertStringContainsString('repositories personnalises', $yamlIssues[0]->getDetail());
    }

    /**
     * Verifie que les problemes de ressources sont de severite WARNING.
     * Toutes les detections doivent etre classees comme avertissements dans la categorie RESOURCE.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Tous les problemes detectes doivent etre des WARNING dans la categorie RESOURCE */
        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
            self::assertSame(Category::RESOURCE, $issue->getCategory());
        }
    }

    /**
     * Verifie que getName() retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new ResourceBundleAnalyzer();

        self::assertSame('Resource Bundle', $analyzer->getName());
    }

    /**
     * Verifie l'estimation du temps pour les ressources YAML.
     * Chaque ressource est estimee a 120 minutes (MINUTES_PER_RESOURCE).
     * Le projet complexe contient 1 ressource, donc 120 minutes.
     */
    #[Test]
    public function testEstimatesCorrectMinutesPerResource(): void
    {
        $analyzer = new ResourceBundleAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche de l'issue YAML pour verifier l'estimation */
        $yamlIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'definition(s) de ressource(s) YAML'),
        ));

        self::assertNotEmpty($yamlIssues);

        /* 1 ressource * 120 minutes = 120 minutes */
        self::assertSame(120, $yamlIssues[0]->getEstimatedMinutes());
    }
}
