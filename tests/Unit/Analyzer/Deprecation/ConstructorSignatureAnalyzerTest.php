<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\ConstructorSignatureAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur de signatures de constructeurs.
 * Verifie la detection des classes etendant des classes Sylius dont le constructeur
 * a change dans Sylius 2.0.
 */
final class ConstructorSignatureAnalyzerTest extends TestCase
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
        $analyzer = new ConstructorSignatureAnalyzer();

        self::assertSame('Constructor Signature', $analyzer->getName());
    }

    /**
     * Verifie que supports retourne false pour un projet sans classes etendant Sylius.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour un projet avec des classes etendant Sylius.
     */
    #[Test]
    public function testSupportsReturnsTrueForModerateProject(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de PriceExtension etendue dans le projet modere.
     */
    #[Test]
    public function testDetectsPriceExtensionInModerateProject(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche d'issues mentionnant PriceExtension */
        $priceIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'PriceExtension'),
        );

        self::assertNotEmpty($priceIssues, 'La classe etendant PriceExtension aurait du etre detectee.');
    }

    /**
     * Verifie que le projet majeur detecte plusieurs classes impactees.
     */
    #[Test]
    public function testDetectsMultipleClassesInMajorProject(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Recherche des issues de detection individuelles (pas le resume) */
        $classIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Classe etendant'),
        );

        /* Le projet majeur contient au moins 5 classes impactees */
        self::assertGreaterThanOrEqual(
            5,
            count($classIssues),
            'Le projet majeur devrait detecter au moins 5 classes.',
        );
    }

    /**
     * Verifie que toutes les issues sont de severite BREAKING et categorie DEPRECATION.
     */
    #[Test]
    public function testAllIssuesAreBreakingInDeprecationCategory(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Verifie l'estimation du temps (120 min par classe).
     */
    #[Test]
    public function testEstimatesCorrectMinutesPerClass(): void
    {
        $analyzer = new ConstructorSignatureAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Recherche de l'issue de synthese */
        $summaryIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'classe(s) etendant des classes Sylius'),
        ));

        self::assertNotEmpty($summaryIssues);

        /* L'estimation doit etre un multiple de 120 */
        $minutes = $summaryIssues[0]->getEstimatedMinutes();
        self::assertSame(0, $minutes % 120, 'L\'estimation doit etre un multiple de 120 minutes.');
        self::assertGreaterThan(0, $minutes);
    }
}
