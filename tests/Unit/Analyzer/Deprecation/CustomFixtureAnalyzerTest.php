<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\CustomFixtureAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des fixtures personnalisees.
 * Verifie la detection des classes dans src/DataFixtures/ et de la configuration sylius_fixtures.
 */
final class CustomFixtureAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('fixture_', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir((string) $item->getRealPath());
            } else {
                unlink((string) $item->getRealPath());
            }
        }

        rmdir($path);
    }

    private function createReport(): MigrationReport
    {
        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12',
            targetVersion: '2.0',
            projectPath: $this->tempDir,
        );
    }

    /**
     * Verifie que supports retourne false sans src/DataFixtures/ ni configuration sylius_fixtures.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutFixtures(): void
    {
        $analyzer = new CustomFixtureAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand src/DataFixtures/ existe.
     */
    #[Test]
    public function testSupportsReturnsTrueWithDataFixturesDir(): void
    {
        mkdir($this->tempDir . '/src/DataFixtures', 0755, true);

        $analyzer = new CustomFixtureAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection des classes de fixtures dans src/DataFixtures/.
     */
    #[Test]
    public function testDetectsDataFixtureClasses(): void
    {
        $fixturesDir = $this->tempDir . '/src/DataFixtures';
        mkdir($fixturesDir, 0755, true);
        file_put_contents($fixturesDir . '/ProductFixture.php', "<?php\nnamespace App\\DataFixtures;\nclass ProductFixture {}\n");

        $analyzer = new CustomFixtureAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $fixtureIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'ProductFixture'),
        );
        self::assertNotEmpty($fixtureIssues);
    }

    /**
     * Verifie que les problemes sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $fixturesDir = $this->tempDir . '/src/DataFixtures';
        mkdir($fixturesDir, 0755, true);
        file_put_contents($fixturesDir . '/ProductFixture.php', "<?php\nclass ProductFixture {}\n");

        $analyzer = new CustomFixtureAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::WARNING, $issue->getSeverity());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new CustomFixtureAnalyzer();

        self::assertSame('Custom Fixture', $analyzer->getName());
    }
}
