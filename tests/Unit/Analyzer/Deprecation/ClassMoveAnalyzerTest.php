<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Deprecation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation\ClassMoveAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour l'analyseur des deplacements de classes entre bundles.
 * Verifie la detection des anciens FQCN dans les fichiers PHP du projet.
 */
final class ClassMoveAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid('classmove_', true);
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
     * Verifie que supports retourne false pour un projet sans src/.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutSrcDirectory(): void
    {
        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne false pour un projet sans classes deplacees.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutMovedClasses(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/SomeService.php', "<?php\nnamespace App;\nclass SomeService {}\n");

        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report));
    }

    /**
     * Verifie que supports retourne true quand un ancien FQCN est utilise.
     */
    #[Test]
    public function testSupportsReturnsTrueWithMovedClass(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ContactService.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ShopBundle\EmailManager\ContactEmailManager;

class ContactService
{
    public function __construct(private readonly ContactEmailManager $manager) {}
}
PHP);

        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report));
    }

    /**
     * Verifie la detection de ContactEmailManager deplace.
     */
    #[Test]
    public function testDetectsContactEmailManagerMove(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/ContactService.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ShopBundle\EmailManager\ContactEmailManager;

class ContactService
{
    public function __construct(private readonly ContactEmailManager $manager) {}
}
PHP);

        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $moveIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getMessage(), 'ContactEmailManager'),
        );
        self::assertNotEmpty($moveIssues);
    }

    /**
     * Verifie la detection de VerifyCustomerAccount deplace.
     */
    #[Test]
    public function testDetectsVerifyCustomerAccountMove(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/VerificationService.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\ApiBundle\Command\Account\VerifyCustomerAccount;

class VerificationService
{
    public function verify(string $token): void
    {
        $command = new VerifyCustomerAccount($token);
    }
}
PHP);

        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        $moveIssues = array_filter(
            $report->getIssues(),
            static fn ($issue): bool => str_contains($issue->getDetail(), 'VerifyCustomerAccount'),
        );
        self::assertNotEmpty($moveIssues);
    }

    /**
     * Verifie que les problemes sont de severite BREAKING.
     */
    #[Test]
    public function testCreatesBreakingIssues(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);
        file_put_contents($srcDir . '/FilterService.php', <<<'PHP'
<?php

namespace App;

use Sylius\Bundle\UiBundle\Storage\FilterStorageInterface;

class FilterService
{
    public function __construct(private readonly FilterStorageInterface $storage) {}
}
PHP);

        $analyzer = new ClassMoveAnalyzer();
        $report = $this->createReport();

        $analyzer->analyze($report);

        foreach ($report->getIssues() as $issue) {
            self::assertSame(Severity::BREAKING, $issue->getSeverity());
        }
    }

    /**
     * Verifie que getName retourne le nom attendu.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new ClassMoveAnalyzer();

        self::assertSame('Class Move', $analyzer->getName());
    }
}
