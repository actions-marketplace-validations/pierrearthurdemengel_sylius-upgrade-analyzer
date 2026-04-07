<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\MultiStoreChannelFixer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour le fixer multi-store channel.
 * Verifie le remplacement de findOneByHostname par findOneEnabledByHostname.
 */
final class MultiStoreChannelFixerTest extends TestCase
{
    private MultiStoreChannelFixer $fixer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixer = new MultiStoreChannelFixer();
        $this->tempDir = sys_get_temp_dir() . '/multi-store-fixer-test-' . uniqid();
        mkdir($this->tempDir . '/src', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createIssue(string $file, ?int $line = null): MigrationIssue
    {
        return new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: 'Multi-Store Channel',
            message: 'Utilisation de findOneByHostname() detectee',
            detail: '',
            suggestion: '',
            file: $file,
            line: $line,
            estimatedMinutes: 240,
        );
    }

    #[Test]
    public function testReplacesFindOneByHostname(): void
    {
        $filePath = $this->tempDir . '/src/Context/ChannelContext.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

class ChannelContext
{
    public function getChannel(): Channel
    {
        return $this->channelRepository->findOneByHostname($hostname);
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Context/ChannelContext.php', 7), $this->tempDir);

        self::assertNotNull($fix);
        self::assertSame(FixConfidence::HIGH, $fix->confidence);
        self::assertStringContainsString('findOneEnabledByHostname', $fix->fixedContent);
        self::assertStringNotContainsString('findOneByHostname', $fix->fixedContent);
    }

    #[Test]
    public function testReturnsNullWhenNoFindOneByHostname(): void
    {
        $filePath = $this->tempDir . '/src/Context/Clean.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

class Clean
{
    public function getChannel(): Channel
    {
        return $this->channelRepository->findOneEnabledByHostname($hostname);
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Context/Clean.php', 7), $this->tempDir);

        self::assertNull($fix);
    }

    #[Test]
    public function testSupportsOnlyPhpFiles(): void
    {
        self::assertTrue($this->fixer->supports($this->createIssue('src/Context/ChannelContext.php')));
        self::assertFalse($this->fixer->supports($this->createIssue('config/services.yaml')));
        self::assertFalse($this->fixer->supports($this->createIssue('templates/base.html.twig')));
    }

    #[Test]
    public function testDoesNotSupportOtherAnalyzers(): void
    {
        $issue = new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: 'Other Analyzer',
            message: 'test',
            detail: '',
            suggestion: '',
            file: 'src/Test.php',
        );

        self::assertFalse($this->fixer->supports($issue));
    }

    #[Test]
    public function testReplacesMultipleOccurrences(): void
    {
        $filePath = $this->tempDir . '/src/Service/MultiChannel.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

class MultiChannel
{
    public function resolve(): void
    {
        $channel = $this->repo->findOneByHostname($host1);
        $other = $this->otherRepo->findOneByHostname($host2);
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Service/MultiChannel.php', 7), $this->tempDir);

        self::assertNotNull($fix);
        self::assertSame(0, substr_count($fix->fixedContent, 'findOneByHostname'));
        self::assertSame(2, substr_count($fix->fixedContent, 'findOneEnabledByHostname'));
    }
}
