<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\AutoFix;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\DoctrineXmlMappingFixer;
use PierreArthur\SyliusUpgradeAnalyzer\AutoFix\FixConfidence;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Tests unitaires pour le fixer de mappings Doctrine XML.
 * Verifie l'ajout du marqueur TODO pour la conversion en attributs PHP.
 */
final class DoctrineXmlMappingFixerTest extends TestCase
{
    private DoctrineXmlMappingFixer $fixer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixer = new DoctrineXmlMappingFixer();
        $this->tempDir = sys_get_temp_dir() . '/doctrine-xml-fixer-test-' . uniqid();
        mkdir($this->tempDir . '/config/doctrine', 0755, true);
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

    private function createIssue(string $file): MigrationIssue
    {
        return new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: 'Doctrine XML Mapping',
            message: 'Mapping XML Doctrine detecte',
            detail: '',
            suggestion: '',
            file: $file,
            estimatedMinutes: 120,
        );
    }

    #[Test]
    public function testAddsTodoCommentToXmlMapping(): void
    {
        $filePath = $this->tempDir . '/config/doctrine/Product.orm.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping">
    <entity name="App\Entity\Product" table="sylius_product">
        <field name="name" type="string" length="255"/>
    </entity>
</doctrine-mapping>
XML);

        $fix = $this->fixer->fix($this->createIssue('config/doctrine/Product.orm.xml'), $this->tempDir);

        self::assertNotNull($fix);
        self::assertSame(FixConfidence::LOW, $fix->confidence);
        self::assertStringContainsString('TODO: convertir en attributs PHP', $fix->fixedContent);
        self::assertStringContainsString('<doctrine-mapping', $fix->fixedContent);
    }

    #[Test]
    public function testReturnsNullWhenAlreadyMarked(): void
    {
        $filePath = $this->tempDir . '/config/doctrine/Order.orm.xml';
        file_put_contents($filePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!-- TODO: convertir en attributs PHP (#[ORM\Entity], #[ORM\Column], etc.) -->
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping">
    <entity name="App\Entity\Order" table="sylius_order"/>
</doctrine-mapping>
XML);

        $fix = $this->fixer->fix($this->createIssue('config/doctrine/Order.orm.xml'), $this->tempDir);

        self::assertNull($fix);
    }

    #[Test]
    public function testSupportsOnlyOrmXmlFiles(): void
    {
        self::assertTrue($this->fixer->supports($this->createIssue('config/doctrine/Product.orm.xml')));
        self::assertFalse($this->fixer->supports($this->createIssue('src/Entity/Product.php')));
        self::assertFalse($this->fixer->supports($this->createIssue('config/services.yaml')));
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
            file: 'config/doctrine/Product.orm.xml',
        );

        self::assertFalse($this->fixer->supports($issue));
    }

    #[Test]
    public function testHandlesXmlWithoutDeclaration(): void
    {
        $filePath = $this->tempDir . '/config/doctrine/Variant.orm.xml';
        file_put_contents($filePath, <<<'XML'
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping">
    <entity name="App\Entity\Variant" table="sylius_product_variant"/>
</doctrine-mapping>
XML);

        $fix = $this->fixer->fix($this->createIssue('config/doctrine/Variant.orm.xml'), $this->tempDir);

        self::assertNotNull($fix);
        self::assertStringStartsWith('<!-- TODO:', $fix->fixedContent);
    }
}
