<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Api\ApiSerializationGroupAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des groupes de serialisation API.
 * Verifie la detection des groupes sans prefixe sylius: dans les fichiers PHP et YAML.
 */
final class ApiSerializationGroupAnalyzerTest extends TestCase
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
     * Verifie que supports retourne false pour le projet trivial sans groupes de serialisation.
     */
    #[Test]
    public function testSupportsReturnsFalseForTrivialProject(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true pour le projet complex avec des groupes non prefixes.
     */
    #[Test]
    public function testSupportsReturnsTrueForComplexProject(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie que l'analyseur detecte les groupes non prefixes dans les fichiers PHP.
     */
    #[Test]
    public function testDetectsNonPrefixedGroupsInPhp(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $phpIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'sans prefixe sylius:')
                && $issue->getFile() !== null
                && str_ends_with($issue->getFile(), '.php'),
        );

        self::assertNotEmpty($phpIssues);
    }

    /**
     * Verifie que l'analyseur detecte les groupes non prefixes dans les fichiers YAML.
     */
    #[Test]
    public function testDetectsNonPrefixedGroupsInYaml(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        $yamlIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'sans prefixe sylius:')
                && $issue->getFile() !== null
                && str_ends_with($issue->getFile(), '.yaml'),
        );

        self::assertNotEmpty($yamlIssues);
    }

    /**
     * Verifie que le projet major genere plus de problemes que le projet complex.
     */
    #[Test]
    public function testMajorProjectHasMoreIssues(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();

        $complexReport = $this->createReportForFixture('project-complex');
        $analyzer->analyze($complexReport);
        $complexCount = count($complexReport->getIssues());

        $majorReport = $this->createReportForFixture('project-major');
        $analyzer->analyze($majorReport);
        $majorCount = count($majorReport->getIssues());

        self::assertGreaterThan($complexCount, $majorCount);
    }

    /**
     * Verifie que tous les problemes sont de severite BREAKING et categorie API.
     */
    #[Test]
    public function testCreatesBreakingApiIssues(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::API, $issue->getCategory());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();

        self::assertSame('API Serialization Group', $analyzer->getName());
    }

    /**
     * Verifie que l'estimation est de 30 minutes par groupe.
     */
    #[Test]
    public function testEstimatesThirtyMinutesPerGroup(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $globalIssues = array_values(array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'groupe(s) de serialisation sans prefixe'),
        ));

        self::assertNotEmpty($globalIssues, 'Le probleme global de synthese devrait etre present.');
        /* Au moins 1 groupe x 30 minutes */
        self::assertGreaterThanOrEqual(30, $globalIssues[0]->getEstimatedMinutes());
    }

    /**
     * Verifie que la suggestion inclut le prefixe sylius: attendu.
     */
    #[Test]
    public function testSuggestionIncludesSyliusPrefix(): void
    {
        $analyzer = new ApiSerializationGroupAnalyzer();
        $report = $this->createReportForFixture('project-complex');

        $analyzer->analyze($report);

        $detailIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'sans prefixe sylius:')
                && $issue->getFile() !== null,
        );

        foreach ($detailIssues as $issue) {
            self::assertStringContainsString('sylius:', $issue->getSuggestion());
        }
    }
}
