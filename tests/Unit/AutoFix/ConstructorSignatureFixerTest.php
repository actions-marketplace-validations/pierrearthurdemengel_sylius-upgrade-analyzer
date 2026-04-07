<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\ConstructorSignatureFixer;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour le fixer de signatures de constructeurs modifiees.
 * Verifie l'ajout de marqueurs TODO sur les constructeurs surcharges.
 */
final class ConstructorSignatureFixerTest extends TestCase
{
    private ConstructorSignatureFixer $fixer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixer = new ConstructorSignatureFixer();
        $this->tempDir = sys_get_temp_dir() . '/constructor-sig-fixer-test-' . uniqid();
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

    private function createIssue(string $file, int $line): MigrationIssue
    {
        return new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: 'Constructor Signature',
            message: 'Classe etendant ZoneMatcher detectee',
            detail: '',
            suggestion: '',
            file: $file,
            line: $line,
            estimatedMinutes: 120,
        );
    }

    #[Test]
    public function testAddsTodoOnConstructor(): void
    {
        $filePath = $this->tempDir . '/src/Addressing/CustomZoneMatcher.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

namespace App\Addressing;

use Sylius\Component\Addressing\Matcher\ZoneMatcher;

class CustomZoneMatcher extends ZoneMatcher
{
    public function __construct(
        private readonly SomeService $service,
    ) {
        parent::__construct();
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Addressing/CustomZoneMatcher.php', 7), $this->tempDir);

        self::assertNotNull($fix);
        self::assertSame(FixConfidence::MEDIUM, $fix->confidence);
        self::assertStringContainsString('TODO: signature du constructeur parent ZoneMatcher', $fix->fixedContent);
        self::assertStringContainsString('modifiee dans Sylius 2.0', $fix->fixedContent);
    }

    #[Test]
    public function testReturnsNullWhenNoConstructor(): void
    {
        $filePath = $this->tempDir . '/src/Addressing/SimpleZoneMatcher.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

namespace App\Addressing;

use Sylius\Component\Addressing\Matcher\ZoneMatcher;

class SimpleZoneMatcher extends ZoneMatcher
{
    public function match(): void
    {
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Addressing/SimpleZoneMatcher.php', 7), $this->tempDir);

        self::assertNull($fix);
    }

    #[Test]
    public function testReturnsNullWhenAlreadyMarked(): void
    {
        $filePath = $this->tempDir . '/src/Addressing/MarkedZoneMatcher.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, <<<'PHP'
<?php

namespace App\Addressing;

use Sylius\Component\Addressing\Matcher\ZoneMatcher;

class MarkedZoneMatcher extends ZoneMatcher
{
    // TODO: signature du constructeur parent ZoneMatcher modifiee dans Sylius 2.0 — adapter les arguments
    public function __construct()
    {
        parent::__construct();
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Addressing/MarkedZoneMatcher.php', 7), $this->tempDir);

        self::assertNull($fix);
    }

    #[Test]
    public function testSupportsOnlyPhpFiles(): void
    {
        self::assertTrue($this->fixer->supports($this->createIssue('src/Test.php', 5)));
        self::assertFalse($this->fixer->supports($this->createIssue('config/services.yaml', 5)));
    }

    #[Test]
    public function testDoesNotSupportOtherAnalyzers(): void
    {
        $issue = new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: 'Other Analyzer',
            message: 'test',
            detail: '',
            suggestion: '',
            file: 'src/Test.php',
            line: 5,
        );

        self::assertFalse($this->fixer->supports($issue));
    }
}
