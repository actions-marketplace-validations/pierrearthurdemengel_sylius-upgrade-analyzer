<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\MultiStoreChannelAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur multi-store/multi-canal.
 * Verifie la detection des configurations multi-canaux et des usages de findOneByHostname.
 */
final class MultiStoreChannelAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('multistore_', true);
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
     * Verifie que supports retourne false sans config/.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutConfigDir(): void
    {
        $analyzer = new MultiStoreChannelAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand sylius_channel est reference.
     */
    #[Test]
    public function testSupportsReturnsTrueWithSyliusChannel(): void
    {
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/sylius_channel.yaml', <<<'YAML'
sylius_channel:
    channels:
        default: ~
        fr: ~
YAML);

        $analyzer = new MultiStoreChannelAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de findOneByHostname() dans le code PHP.
     */
    #[Test]
    public function testDetectsFindOneByHostnameUsage(): void
    {
        /* Creation du fichier de config pour supports() */
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/sylius_channel.yaml', "sylius_channel: ~\n");

        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ChannelResolver.php', <<<'PHP'
<?php

namespace App;

class ChannelResolver
{
    public function resolve($repository, string $hostname)
    {
        return $repository->findOneByHostname($hostname);
    }
}
PHP);

        $analyzer = new MultiStoreChannelAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $hostnameIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'findOneByHostname'),
        );
        self::assertNotEmpty($hostnameIssues);
    }

    /**
     * Verifie que les problemes sont de severite WARNING.
     */
    #[Test]
    public function testCreatesWarningIssues(): void
    {
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/sylius_channel.yaml', "sylius_channel: ~\n");

        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ChannelResolver.php', <<<'PHP'
<?php

namespace App;

class ChannelResolver
{
    public function resolve($repository, string $hostname)
    {
        return $repository->findOneByHostname($hostname);
    }
}
PHP);

        $analyzer = new MultiStoreChannelAnalyzer();
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
        $analyzer = new MultiStoreChannelAnalyzer();

        self::assertSame('Multi-Store Channel', $analyzer->getName());
    }
}
