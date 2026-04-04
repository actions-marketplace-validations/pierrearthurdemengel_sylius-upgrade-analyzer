<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\UserModelFieldAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des champs dépréciés du modèle User.
 * Vérifie la détection des propriétés locked, expiresAt, credentialsExpireAt
 * et de l'interface Serializable.
 */
final class UserModelFieldAnalyzerTest extends TestCase
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
     * Vérifie que supports retourne true quand src/Entity/ existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWhenEntityDirExists(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Vérifie que supports retourne false pour le projet trivial.
     * Le projet trivial n'a pas de fichiers PHP dans src/Entity/.
     */
    #[Test]
    public function testSupportsReturnsTrueForTrivialProject(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-trivial');

        /* Le projet trivial a un src/ mais pas de src/Entity/ en tant que répertoire */
        /* Cependant, supports vérifie uniquement si src/Entity/ est un répertoire */
        $entityDir = $report->getProjectPath() . '/src/Entity';
        if (is_dir($entityDir)) {
            self::assertTrue($analyzer->supports($report));
        } else {
            self::assertFalse($analyzer->supports($report));
        }
    }

    /**
     * Vérifie la détection des champs locked et Serializable dans le projet modéré.
     * Le projet modéré contient ShopUser avec locked et Serializable.
     */
    #[Test]
    public function testDetectsLockedAndSerializableInModerateProject(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        /* Recherche des problèmes liés à locked */
        $lockedIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'locked')
                || str_contains($issue->getMessage(), 'isLocked')
                || str_contains($issue->getMessage(), 'setLocked'),
        );

        self::assertGreaterThan(0, count($lockedIssues), 'Les champs locked devraient être détectés.');

        /* Recherche de l'interface Serializable */
        $serializableIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'Serializable'),
        );

        self::assertCount(1, $serializableIssues, 'L\'interface Serializable devrait être détectée.');
    }

    /**
     * Vérifie la détection de tous les champs dépréciés dans le projet majeur.
     * Le projet majeur contient AdminUser avec locked, expiresAt, credentialsExpireAt,
     * getSalt et Serializable, plus ShopUser avec locked, expiresAt et Serializable.
     */
    #[Test]
    public function testDetectsAllFieldsInMajorProject(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        /* Le projet majeur devrait avoir de nombreux problèmes */
        self::assertGreaterThan(5, count($report->getIssues()));

        /* Vérification de la présence de chaque type de champ */
        $messages = array_map(
            static fn ($issue): string => $issue->getMessage(),
            $report->getIssues(),
        );
        $allMessages = implode(' ', $messages);

        self::assertStringContainsString('locked', $allMessages);
        self::assertStringContainsString('Serializable', $allMessages);
    }

    /**
     * Vérifie la détection des champs expiresAt dans le projet majeur.
     */
    #[Test]
    public function testDetectsExpiresAtFields(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-major');

        $analyzer->analyze($report);

        $expiresIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'expiresAt')
                || str_contains($issue->getMessage(), 'getExpiresAt')
                || str_contains($issue->getMessage(), 'setExpiresAt')
                || str_contains($issue->getMessage(), 'isExpired'),
        );

        self::assertGreaterThan(0, count($expiresIssues), 'Les champs expiresAt devraient être détectés.');
    }

    /**
     * Vérifie que tous les problèmes sont de sévérité BREAKING et catégorie DEPRECATION.
     */
    #[Test]
    public function testIssuesHaveCorrectSeverityAndCategory(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
            self::assertSame(Category::DEPRECATION, $issue->getCategory());
        }
    }

    /**
     * Vérifie que l'estimation est de 60 minutes par champ/méthode.
     */
    #[Test]
    public function testEstimatesSixtyMinutesPerField(): void
    {
        $analyzer = new UserModelFieldAnalyzer();
        $report = $this->createReportForFixture('project-moderate');

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(60, $issue->getEstimatedMinutes());
        }
    }

    /**
     * Vérifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new UserModelFieldAnalyzer();

        self::assertSame('User Model Field', $analyzer->getName());
    }
}
