<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\UserEncoderAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des encodeurs de mots de passe dépréciés.
 * Vérifie la détection de la configuration "encoders:" et de la méthode getSalt().
 */
final class UserEncoderAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne toujours true.
     * L'analyseur est applicable à tous les projets Symfony car il vérifie security.yaml.
     */
    #[Test]
    public function testSupportsAlwaysReturnsTrue(): void
    {
        $analyzer = new UserEncoderAnalyzer();

        /* Vérifie pour le projet trivial */
        $reportTrivial = $this->createReportForFixture('project-trivial');
        self::assertTrue($analyzer->supports($reportTrivial));

        /* Vérifie pour le projet complexe */
        $reportComplex = $this->createReportForFixture('project-complex');
        self::assertTrue($analyzer->supports($reportComplex));
    }

    /**
     * Vérifie la détection de la syntaxe "encoders:" dépréciée dans security.yaml.
     * Le projet complexe utilise "encoders:" au lieu de "password_hashers:".
     */
    #[Test]
    public function testDetectsOldEncoderSyntax(): void
    {
        $analyzer = new UserEncoderAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème mentionnant les encoders dépréciés */
        $encoderIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'encoders'),
        );

        self::assertNotEmpty($encoderIssues, 'La configuration "encoders:" aurait dû être détectée.');
    }

    /**
     * Vérifie la détection de la méthode getSalt() dans les entités.
     * Le projet complexe contient Customer.php avec une méthode getSalt().
     */
    #[Test]
    public function testDetectsGetSaltMethod(): void
    {
        $analyzer = new UserEncoderAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Recherche du problème mentionnant getSalt() */
        $saltIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'getSalt()'),
        );

        self::assertNotEmpty($saltIssues, 'La méthode getSalt() aurait dû être détectée.');
    }

    /**
     * Vérifie qu'aucun problème n'est détecté pour un projet déjà migré.
     * Le projet trivial utilise "password_hashers:" et n'a pas de méthode getSalt().
     */
    #[Test]
    public function testNoIssuesForMigratedProject(): void
    {
        $analyzer = new UserEncoderAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        /* Le projet trivial utilise password_hashers et n'a pas de getSalt(), donc 0 problèmes */
        self::assertCount(0, $report->getIssues());
    }

    /**
     * Vérifie que tous les problèmes créés sont de sévérité WARNING.
     * La migration des encodeurs est un avertissement, pas un changement cassant immédiat.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $analyzer = new UserEncoderAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        /* Tous les problèmes détectés doivent être de sévérité WARNING */
        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new UserEncoderAnalyzer();

        self::assertSame('User Encoder', $analyzer->getName());
    }
}
