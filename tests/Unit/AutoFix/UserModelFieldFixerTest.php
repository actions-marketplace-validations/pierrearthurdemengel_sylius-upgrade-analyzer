<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\UserModelFieldFixer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour le fixer de champs deprecies du modele User.
 * Verifie le commentaire des proprietes et methodes supprimees dans Sylius 2.0.
 */
final class UserModelFieldFixerTest extends TestCase
{
    private UserModelFieldFixer $fixer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixer = new UserModelFieldFixer();
        $this->tempDir = sys_get_temp_dir() . '/user-model-fixer-test-' . uniqid();
        mkdir($this->tempDir . '/src/Entity', 0755, true);
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
            analyzer: 'User Model Field',
            message: 'Propriete depreciee detectee',
            detail: '',
            suggestion: '',
            file: $file,
            line: $line,
            estimatedMinutes: 60,
        );
    }

    #[Test]
    public function testCommentsDeprecatedProperty(): void
    {
        $filePath = $this->tempDir . '/src/Entity/User.php';
        file_put_contents($filePath, <<<'PHP'
<?php

class User
{
    private bool $locked = false;

    public function isLocked(): bool
    {
        return $this->locked;
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Entity/User.php', 5), $this->tempDir);

        self::assertNotNull($fix);
        self::assertSame(FixConfidence::MEDIUM, $fix->confidence);
        self::assertStringContainsString('TODO: propriete supprimee dans Sylius 2.0', $fix->fixedContent);
    }

    #[Test]
    public function testCommentsDeprecatedMethod(): void
    {
        $filePath = $this->tempDir . '/src/Entity/AdminUser.php';
        file_put_contents($filePath, <<<'PHP'
<?php

class AdminUser
{
    public function isLocked(): bool
    {
        return false;
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Entity/AdminUser.php', 5), $this->tempDir);

        self::assertNotNull($fix);
        self::assertStringContainsString('TODO: methode supprimee dans Sylius 2.0', $fix->fixedContent);
    }

    #[Test]
    public function testFixesSerializableInterface(): void
    {
        $filePath = $this->tempDir . '/src/Entity/ShopUser.php';
        file_put_contents($filePath, <<<'PHP'
<?php

class ShopUser implements UserInterface, Serializable
{
    public function serialize(): string
    {
        return '';
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Entity/ShopUser.php', 3), $this->tempDir);

        self::assertNotNull($fix);
        self::assertStringNotContainsString('Serializable', $fix->fixedContent);
        self::assertStringContainsString('UserInterface', $fix->fixedContent);
    }

    #[Test]
    public function testReturnsNullForCleanFile(): void
    {
        $filePath = $this->tempDir . '/src/Entity/Product.php';
        file_put_contents($filePath, <<<'PHP'
<?php

class Product
{
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Entity/Product.php', 5), $this->tempDir);

        self::assertNull($fix);
    }

    #[Test]
    public function testSupportsOnlyPhpFiles(): void
    {
        self::assertTrue($this->fixer->supports($this->createIssue('src/Entity/User.php', 5)));
        self::assertFalse($this->fixer->supports($this->createIssue('config/services.yaml', 5)));
    }

    #[Test]
    public function testCommentsExpiresAtProperty(): void
    {
        $filePath = $this->tempDir . '/src/Entity/User2.php';
        file_put_contents($filePath, <<<'PHP'
<?php

class User2
{
    protected ?\DateTimeInterface $expiresAt = null;
}
PHP);

        $fix = $this->fixer->fix($this->createIssue('src/Entity/User2.php', 5), $this->tempDir);

        self::assertNotNull($fix);
        self::assertStringContainsString('TODO: propriete supprimee dans Sylius 2.0', $fix->fixedContent);
    }
}
