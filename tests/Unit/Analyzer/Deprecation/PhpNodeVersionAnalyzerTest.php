<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\PhpNodeVersionAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de versions PHP, Node.js et Symfony.
 * Verifie la detection des contraintes de versions incompatibles avec Sylius 2.0.
 */
final class PhpNodeVersionAnalyzerTest extends TestCase
{
    /** Chemin vers le repertoire des fixtures */
    private const FIXTURES_PATH = __DIR__ . '/../../../Fixtures';

    /**
     * Cree un rapport de migration pointant vers le projet de fixture specifie.
     */
    private function createReportForFixture(string $projectName): MigrationReport
    {
        $path = realpath(self::FIXTURES_PATH . '/' . $projectName);
        self::assertNotFalse($path, sprintf('Le repertoire de fixture "%s" est introuvable.', $projectName));

        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: null,
            targetVersion: '2.0',
            projectPath: $path,
        );
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();

        self::assertSame('PHP Node Version', $analyzer->getName());
    }

    /**
     * Verifie que supports retourne true pour tout projet avec composer.json.
     */
    #[Test]
    public function testSupportsReturnsTrueWithComposerJson(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de la contrainte PHP >=8.1 dans le projet trivial.
     */
    #[Test]
    public function testDetectsOldPhpConstraint(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant la contrainte PHP */
        $phpIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Contrainte PHP'),
        );

        self::assertNotEmpty($phpIssues, 'La contrainte PHP trop permissive aurait du etre detectee.');
    }

    /**
     * Verifie la detection de la contrainte Node.js dans le projet majeur.
     */
    #[Test]
    public function testDetectsOldNodeConstraint(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant Node.js */
        $nodeIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Contrainte Node.js'),
        );

        self::assertNotEmpty($nodeIssues, 'La contrainte Node.js trop permissive aurait du etre detectee.');
    }

    /**
     * Verifie la detection des dependances Symfony 5.4 dans le projet majeur.
     */
    #[Test]
    public function testDetectsSymfony54Dependencies(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant Symfony 5.4 */
        $symfonyIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Dependance Symfony 5.4'),
        );

        self::assertNotEmpty($symfonyIssues, 'Les dependances Symfony 5.4 auraient du etre detectees.');

        /* Le projet majeur a au moins 3 paquets symfony/* en ^5.4 */
        self::assertGreaterThanOrEqual(
            3,
            count($symfonyIssues),
            'Au moins 3 dependances Symfony 5.4 auraient du etre detectees.',
        );
    }

    /**
     * Verifie que toutes les issues sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testAllIssuesAreBreakingInDeprecationCategory(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Verifie que le projet majeur genere plus d'issues que le projet trivial.
     */
    #[Test]
    public function testMajorProjectGeneratesMoreIssues(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();

        $trivialReport = $this->createReportForFixture('project-trivial');
        $analyzer->analyze($trivialReport);
        $trivialCount = count($trivialReport->getIssues());

        $majorReport = $this->createReportForFixture('project-major');
        $analyzer->analyze($majorReport);
        $majorCount = count($majorReport->getIssues());

        self::assertGreaterThan(
            $trivialCount,
            $majorCount,
            'Le projet majeur devrait generer plus d\'issues que le projet trivial.',
        );
    }

    /**
     * Verifie l'estimation du temps (30 min par probleme de version).
     */
    #[Test]
    public function testEstimatesCorrectMinutesPerVersionIssue(): void
    {
        $analyzer = new PhpNodeVersionAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Recherche de l'issue de synthese */
        $summaryIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'probleme(s) de version'),
        ));

        self::assertNotEmpty($summaryIssues);

        /* L'estimation doit etre un multiple de 30 */
        $minutes = $summaryIssues[0]->getEstimatedMinutes();
        self::assertSame(0, $minutes % 30, 'L\'estimation doit etre un multiple de 30 minutes.');
        self::assertGreaterThan(0, $minutes);
    }
}
