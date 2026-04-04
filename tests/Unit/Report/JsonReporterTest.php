<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Report;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use PierreArthur\SyliusUpgradeAnalyzer\Report\JsonReporter;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests unitaires pour le générateur de rapport JSON.
 * Vérifie la structure, le contenu et l'écriture du rapport au format JSON.
 */
final class JsonReporterTest extends TestCase
{
    /**
     * Crée un rapport de migration pour les tests.
     */
    private function createReport(): MigrationReport
    {
        return new MigrationReport(
            startedAt: new \DateTimeImmutable('2025-01-15 10:00:00'),
            detectedSyliusVersion: 'v1.14.0',
            targetVersion: '2.2',
            projectPath: '/tmp/test-project',
        );
    }

    /**
     * Crée un rapport avec des problèmes de test prédéfinis.
     */
    private function createReportWithIssues(): MigrationReport
    {
        $report = $this->createReport();

        /* Ajout d'un problème BREAKING dans la catégorie DEPRECATION */
        $report->addIssue(new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: 'SwiftMailer',
            message: 'Dependance swiftmailer detectee',
            detail: 'SwiftMailer est abandonne.',
            suggestion: 'Migrer vers Symfony Mailer.',
            file: 'composer.json',
            estimatedMinutes: 120,
        ));

        /* Ajout d'un problème WARNING dans la catégorie PLUGIN */
        $report->addIssue(new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::PLUGIN,
            analyzer: 'Plugin Compatibility',
            message: 'Compatibilite inconnue pour le plugin',
            detail: 'Le plugin X n\'a pas pu etre verifie.',
            suggestion: 'Verifier manuellement.',
            estimatedMinutes: 240,
        ));

        /* Ajout d'un problème SUGGESTION dans la catégorie PLUGIN */
        $report->addIssue(new MigrationIssue(
            severity: Severity::SUGGESTION,
            category: Category::PLUGIN,
            analyzer: 'Plugin Compatibility',
            message: 'Plugin compatible detecte',
            detail: 'Le plugin Y est compatible.',
            suggestion: 'Mettre a jour vers la version 2.0.',
            estimatedMinutes: 30,
        ));

        return $report;
    }

    /**
     * Décode la sortie JSON capturée par le BufferedOutput.
     *
     * @return array<string, mixed>
     */
    private function generateAndDecode(MigrationReport $report, array $context = []): array
    {
        $reporter = new JsonReporter();
        $output = new BufferedOutput();

        $reporter->generate($report, $output, $context);

        $json = $output->fetch();
        $data = json_decode($json, true);
        self::assertIsArray($data, 'La sortie devrait être du JSON valide.');

        return $data;
    }

    /**
     * Vérifie que getFormat retourne 'json'.
     */
    #[Test]
    public function testGetFormatReturnsJson(): void
    {
        $reporter = new JsonReporter();

        self::assertSame('json', $reporter->getFormat());
    }

    /**
     * Vérifie que generate produit du JSON valide.
     */
    #[Test]
    public function testGenerateOutputsValidJson(): void
    {
        $report = $this->createReportWithIssues();
        $reporter = new JsonReporter();
        $output = new BufferedOutput();

        $reporter->generate($report, $output);

        $json = $output->fetch();
        $data = json_decode($json, true);

        /* La sortie doit être du JSON valide */
        self::assertNotNull($data, 'La sortie devrait être du JSON valide.');
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Vérifie la présence de la section "meta" avec les métadonnées du rapport.
     */
    #[Test]
    public function testGenerateIncludesMetaSection(): void
    {
        $report = $this->createReportWithIssues();
        $data = $this->generateAndDecode($report);

        /* La section meta doit être présente */
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('version', $data['meta']);
        self::assertArrayHasKey('target_version', $data['meta']);
        self::assertArrayHasKey('analyzed_at', $data['meta']);

        /* Vérification des valeurs attendues */
        self::assertSame('v1.14.0', $data['meta']['version']);
        self::assertSame('2.2', $data['meta']['target_version']);
    }

    /**
     * Vérifie la présence de la section "summary" avec les compteurs.
     */
    #[Test]
    public function testGenerateIncludesSummary(): void
    {
        $report = $this->createReportWithIssues();
        $data = $this->generateAndDecode($report);

        /* La section summary doit être présente */
        self::assertArrayHasKey('summary', $data);
        self::assertArrayHasKey('complexity', $data['summary']);
        self::assertArrayHasKey('total_hours', $data['summary']);
        self::assertArrayHasKey('issues_count', $data['summary']);
        self::assertArrayHasKey('breaking_count', $data['summary']);
        self::assertArrayHasKey('warning_count', $data['summary']);
        self::assertArrayHasKey('suggestion_count', $data['summary']);

        /* Vérification des compteurs */
        self::assertSame(3, $data['summary']['issues_count']);
        self::assertSame(1, $data['summary']['breaking_count']);
        self::assertSame(1, $data['summary']['warning_count']);
        self::assertSame(1, $data['summary']['suggestion_count']);
    }

    /**
     * Vérifie la présence de la section "issues" regroupée par catégorie.
     */
    #[Test]
    public function testGenerateIncludesIssues(): void
    {
        $report = $this->createReportWithIssues();
        $data = $this->generateAndDecode($report);

        /* La section issues doit être présente */
        self::assertArrayHasKey('issues', $data);

        /* Les catégories DEPRECATION et PLUGIN doivent être présentes */
        self::assertArrayHasKey('deprecation', $data['issues']);
        self::assertArrayHasKey('plugin', $data['issues']);

        /* 1 problème DEPRECATION, 2 problèmes PLUGIN */
        self::assertCount(1, $data['issues']['deprecation']);
        self::assertCount(2, $data['issues']['plugin']);

        /* Vérification de la structure d'un problème */
        $firstIssue = $data['issues']['deprecation'][0];
        self::assertArrayHasKey('severity', $firstIssue);
        self::assertArrayHasKey('category', $firstIssue);
        self::assertArrayHasKey('analyzer', $firstIssue);
        self::assertArrayHasKey('message', $firstIssue);
        self::assertArrayHasKey('detail', $firstIssue);
        self::assertArrayHasKey('suggestion', $firstIssue);
    }

    /**
     * Vérifie la présence de la section "estimated_hours_by_category".
     * Seuls les problèmes BREAKING et WARNING sont comptabilisés.
     */
    #[Test]
    public function testGenerateIncludesEstimatedHoursByCategory(): void
    {
        $report = $this->createReportWithIssues();
        $data = $this->generateAndDecode($report);

        /* La section des heures estimées par catégorie doit être présente */
        self::assertArrayHasKey('estimated_hours_by_category', $data);

        /* La catégorie DEPRECATION a 120 minutes (2 heures) de BREAKING */
        self::assertArrayHasKey('deprecation', $data['estimated_hours_by_category']);
        self::assertEquals(2.0, $data['estimated_hours_by_category']['deprecation']);

        /* La catégorie PLUGIN a 240 minutes (4 heures) de WARNING */
        self::assertArrayHasKey('plugin', $data['estimated_hours_by_category']);
        self::assertEquals(4.0, $data['estimated_hours_by_category']['plugin']);
    }

    /**
     * Vérifie l'écriture du rapport dans un fichier temporaire.
     * Le fichier doit contenir du JSON valide et un message de confirmation est affiché.
     */
    #[Test]
    public function testGenerateWritesToFile(): void
    {
        $report = $this->createReportWithIssues();
        $reporter = new JsonReporter();
        $output = new BufferedOutput();

        /* Création d'un chemin de fichier temporaire */
        $tempFile = sys_get_temp_dir() . '/sylius-test-report-' . uniqid() . '.json';

        try {
            $reporter->generate($report, $output, ['output_file' => $tempFile]);

            /* Le fichier doit exister */
            self::assertFileExists($tempFile);

            /* Le contenu du fichier doit être du JSON valide */
            $content = file_get_contents($tempFile);
            self::assertIsString($content);

            $data = json_decode($content, true);
            self::assertIsArray($data);
            self::assertArrayHasKey('meta', $data);
            self::assertArrayHasKey('summary', $data);
            self::assertArrayHasKey('issues', $data);

            /* La sortie console doit contenir le message de confirmation */
            $consoleOutput = $output->fetch();
            self::assertStringContainsString('Rapport JSON genere dans', $consoleOutput);
        } finally {
            /* Nettoyage du fichier temporaire */
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Vérifie le comportement avec un rapport vide (aucun problème).
     * Le JSON doit contenir des sections vides mais rester structurellement valide.
     */
    #[Test]
    public function testGenerateWithEmptyReport(): void
    {
        $report = $this->createReport();
        $data = $this->generateAndDecode($report);

        /* Le rapport doit être structurellement complet */
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('summary', $data);
        self::assertArrayHasKey('issues', $data);
        self::assertArrayHasKey('estimated_hours_by_category', $data);

        /* Aucun problème détecté */
        self::assertSame(0, $data['summary']['issues_count']);
        self::assertSame(0, $data['summary']['breaking_count']);
        self::assertSame(0, $data['summary']['warning_count']);
        self::assertSame(0, $data['summary']['suggestion_count']);
        self::assertEquals(0.0, $data['summary']['total_hours']);

        /* Aucune catégorie de problèmes */
        self::assertEmpty($data['issues']);
        self::assertEmpty($data['estimated_hours_by_category']);
    }

    /**
     * Vérifie que la complexité est correctement reflétée dans le résumé.
     */
    #[Test]
    public function testGenerateReflectsComplexity(): void
    {
        $report = $this->createReport();
        $data = $this->generateAndDecode($report);

        /* Un rapport vide doit avoir une complexité triviale */
        self::assertSame('trivial', $data['summary']['complexity']);
    }
}
