<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use PierreArthur\SyliusUpgradeAnalyzer\Rules\CustomRuleAnalyzer;
use PierreArthur\SyliusUpgradeAnalyzer\Rules\CustomRuleLoader;

/**
 * Tests unitaires pour l'analyseur de regles personnalisees.
 * Verifie l'execution des differents types de regles (PHP, YAML, Twig)
 * et la creation correcte des issues dans le rapport de migration.
 */
final class CustomRuleAnalyzerTest extends TestCase
{
    /** Repertoire temporaire utilise pour les tests */
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sylius-rule-analyzer-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        /* Nettoyage du repertoire temporaire apres chaque test */
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Supprime recursivement un repertoire et son contenu.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }

    /**
     * Ecrit un fichier de regles YAML dans le repertoire temporaire.
     */
    private function writeRulesFile(string $content): void
    {
        file_put_contents($this->tempDir . '/.sylius-upgrade-rules.yaml', $content);
    }

    /**
     * Cree un rapport de migration pointant vers le repertoire temporaire.
     */
    private function createReport(): MigrationReport
    {
        return new MigrationReport(
            startedAt: new \DateTimeImmutable(),
            detectedSyliusVersion: '1.12.0',
            targetVersion: '2.2',
            projectPath: $this->tempDir,
        );
    }

    /**
     * Verifie que getName retourne le nom attendu de l'analyseur.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());

        self::assertSame('Custom Rules', $analyzer->getName());
    }

    /**
     * Verifie que supports retourne true quand le fichier de regles existe.
     * La presence de .sylius-upgrade-rules.yaml active l'analyseur.
     */
    #[Test]
    public function testSupportsReturnsTrueWithRulesFile(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules: []
YAML);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        self::assertTrue($analyzer->supports($report), 'L\'analyseur devrait etre supporte quand le fichier de regles existe.');
    }

    /**
     * Verifie que supports retourne false quand le fichier de regles est absent.
     * Sans fichier .sylius-upgrade-rules.yaml, l'analyseur ne doit pas s'executer.
     */
    #[Test]
    public function testSupportsReturnsFalseWithoutRulesFile(): void
    {
        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        self::assertFalse($analyzer->supports($report), 'L\'analyseur ne devrait pas etre supporte sans fichier de regles.');
    }

    /**
     * Verifie la detection d'utilisation de classe PHP via une regle php_class_usage.
     * Cree un fichier PHP contenant une utilisation de classe et verifie
     * que la regle detecte la correspondance.
     */
    #[Test]
    public function testAnalyzeWithPhpClassUsageRule(): void
    {
        /* Creation de la regle ciblant une classe specifique */
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "deprecated-old-service"
      pattern: "App\\Legacy\\OldService"
      type: "php_class_usage"
      severity: "breaking"
      category: "deprecation"
      message: "La classe OldService est supprimee"
      suggestion: "Utiliser App\\Service\\NewService"
      estimated_minutes: 15
YAML);

        /* Creation d'un fichier PHP utilisant la classe ciblee */
        file_put_contents($this->tempDir . '/TestFile.php', <<<'PHP'
<?php

namespace App\Controller;

use App\Legacy\OldService;

class TestController
{
    public function __construct(private OldService $service) {}
}
PHP);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        $analyzer->analyze($report);

        $issues = $report->getIssues();
        self::assertNotEmpty($issues, 'La regle php_class_usage devrait detecter l\'utilisation de OldService.');

        /* Verification que le message contient le nom de la regle */
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue->getMessage(), 'deprecated-old-service')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Le message de l\'issue devrait contenir le nom de la regle.');
    }

    /**
     * Verifie la detection de cles YAML via une regle yaml_key.
     * Cree un fichier YAML contenant une cle ciblee et verifie
     * que la regle detecte la correspondance.
     */
    #[Test]
    public function testAnalyzeWithYamlKeyRule(): void
    {
        /* Creation de la regle ciblant une cle YAML specifique */
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "deprecated-config-key"
      pattern: "old_grid_driver"
      type: "yaml_key"
      severity: "warning"
      category: "grid"
      message: "La cle old_grid_driver est depreciee"
      suggestion: "Utiliser la nouvelle syntaxe de configuration de grille"
      estimated_minutes: 10
YAML);

        /* Creation d'un fichier YAML contenant la cle ciblee */
        file_put_contents($this->tempDir . '/config.yaml', <<<'YAMLCONTENT'
parameters:
    old_grid_driver: doctrine/orm
    new_setting: true
YAMLCONTENT);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        $analyzer->analyze($report);

        $issues = $report->getIssues();
        self::assertNotEmpty($issues, 'La regle yaml_key devrait detecter la cle old_grid_driver.');
    }

    /**
     * Verifie la detection de fonctions Twig via une regle twig_function.
     * Cree un fichier Twig contenant un appel de fonction cible et verifie
     * que la regle detecte la correspondance.
     */
    #[Test]
    public function testAnalyzeWithTwigFunctionRule(): void
    {
        /* Creation de la regle ciblant une fonction Twig specifique */
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "deprecated-twig-function"
      pattern: "sylius_template_event"
      type: "twig_function"
      severity: "breaking"
      category: "twig"
      message: "La fonction sylius_template_event est supprimee dans Sylius 2.x"
      suggestion: "Utiliser le systeme de hooks Twig Symfony UX"
      estimated_minutes: 20
YAML);

        /* Creation d'un fichier Twig utilisant la fonction ciblee */
        file_put_contents($this->tempDir . '/template.html.twig', <<<'TWIG'
{% extends '@SyliusShop/layout.html.twig' %}

{% block content %}
    {{ sylius_template_event('sylius.shop.product.show') }}
    <div>Contenu du template</div>
{% endblock %}
TWIG);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        $analyzer->analyze($report);

        $issues = $report->getIssues();
        self::assertNotEmpty($issues, 'La regle twig_function devrait detecter l\'appel a sylius_template_event.');
    }

    /**
     * Verifie que la severite des issues correspond a celle definie dans la regle.
     * Une regle avec severity "breaking" doit creer des issues BREAKING.
     */
    #[Test]
    public function testCreatesCorrectSeverityIssues(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "breaking-rule"
      pattern: "critical_config"
      type: "yaml_key"
      severity: "breaking"
      category: "deprecation"
      message: "Configuration critique supprimee"
      suggestion: "Migrer vers la nouvelle configuration"
      estimated_minutes: 60
YAML);

        /* Creation d'un fichier YAML declenchant la regle */
        file_put_contents($this->tempDir . '/services.yaml', <<<'YAMLCONTENT'
services:
    critical_config: legacy_value
YAMLCONTENT);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        $analyzer->analyze($report);

        $issues = $report->getIssues();
        self::assertNotEmpty($issues, 'La regle devrait detecter la cle critical_config.');

        /* Verification que la severite est bien BREAKING */
        $breakingIssues = array_filter(
            $issues,
            static fn ($issue): bool => $issue->getSeverity() === Severity::BREAKING,
        );
        self::assertNotEmpty($breakingIssues, 'La severite de l\'issue devrait etre BREAKING.');
    }

    /**
     * Verifie que l'estimation en minutes est correctement reportee dans les issues.
     * Le champ estimatedMinutes de la regle doit se retrouver dans l'issue creee.
     */
    #[Test]
    public function testEstimatesCorrectMinutes(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "timed-rule"
      pattern: "slow_migration_key"
      type: "yaml_key"
      severity: "warning"
      category: "resource"
      message: "Migration longue necessaire"
      suggestion: "Suivre le guide de migration"
      estimated_minutes: 45
YAML);

        /* Creation d'un fichier YAML declenchant la regle */
        file_put_contents($this->tempDir . '/config.yml', <<<'YAMLCONTENT'
parameters:
    slow_migration_key: old_value
YAMLCONTENT);

        $analyzer = new CustomRuleAnalyzer(new CustomRuleLoader());
        $report = $this->createReport();

        $analyzer->analyze($report);

        $issues = $report->getIssues();
        self::assertNotEmpty($issues, 'La regle devrait detecter la cle slow_migration_key.');

        /* Verification que l'estimation en minutes est correcte */
        $issue = $issues[0];
        self::assertSame(45, $issue->getEstimatedMinutes(), 'L\'estimation en minutes devrait etre de 45.');
    }
}
