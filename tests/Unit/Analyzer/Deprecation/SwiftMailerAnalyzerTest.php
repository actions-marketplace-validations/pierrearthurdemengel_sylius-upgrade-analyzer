<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\SwiftMailerAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de SwiftMailer.
 * Vérifie la détection des dépendances, configurations et usages de SwiftMailer.
 */
final class SwiftMailerAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne true pour le projet complexe qui contient SwiftMailer.
     * Le composer.json du projet complexe déclare swiftmailer/swiftmailer.
     */
    #[Test]
    public function testSupportsReturnsTrueForProjectWithSwiftMailer(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne false pour le projet trivial sans SwiftMailer.
     * Le composer.json du projet trivial ne déclare pas swiftmailer/swiftmailer.
     */
    #[Test]
    public function testSupportsReturnsFalseForProjectWithoutSwiftMailer(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Vérifie la détection de la dépendance SwiftMailer dans composer.json.
     * L'analyseur doit créer un problème spécifique pour la dépendance.
     */
    #[Test]
    public function testDetectsSwiftMailerDependency(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème mentionnant la dépendance composer */
        $composerIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'swiftmailer/swiftmailer detectee dans composer.json'),
        );

        self::assertNotEmpty($composerIssues, 'La dépendance SwiftMailer aurait dû être détectée.');
    }

    /**
     * Vérifie la détection de la configuration SwiftMailer dans les fichiers YAML.
     * Le projet complexe contient un fichier swiftmailer.yaml avec la clé swiftmailer:.
     */
    #[Test]
    public function testDetectsSwiftMailerConfig(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème mentionnant la configuration YAML */
        $configIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Configuration swiftmailer detectee'),
        );

        self::assertNotEmpty($configIssues, 'La configuration SwiftMailer aurait dû être détectée.');
    }

    /**
     * Vérifie que tous les problèmes créés par l'analyseur sont de sévérité BREAKING.
     * SwiftMailer est abandonné, donc tous les problèmes sont des changements cassants.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
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
        $analyzer = new SwiftMailerAnalyzer();

        self::assertSame('SwiftMailer', $analyzer->getName());
    }

    /**
     * Vérifie que le problème global contient le nombre total d'usages détectés.
     * Le projet complexe a au moins 2 usages : dépendance composer + configuration YAML.
     */
    #[Test]
    public function testGlobalIssueSummarizesAllUsages(): void
    {
        $analyzer = new SwiftMailerAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème global résumant les usages */
        $globalIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'usage(s) de SwiftMailer detecte(s)'),
        );

        self::assertNotEmpty($globalIssues, 'Le problème global devrait être présent.');
    }
}
