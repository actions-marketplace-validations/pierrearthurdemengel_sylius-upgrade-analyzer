<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Rules\CustomRule;
use PierreArthur\SyliusUpgradeAnalyzer\Rules\CustomRuleLoader;

/**
 * Tests unitaires pour le chargeur de regles personnalisees.
 * Verifie le chargement depuis le fichier YAML, la validation des champs
 * requis et la gestion des erreurs de syntaxe.
 */
final class CustomRuleLoaderTest extends TestCase
{
    /** Repertoire temporaire utilise pour les tests */
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sylius-rule-loader-test-' . uniqid();
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
     * Verifie que load retourne un tableau vide quand le fichier de regles est absent.
     * Sans fichier .sylius-upgrade-rules.yaml, aucune regle ne doit etre chargee.
     */
    #[Test]
    public function testLoadReturnsEmptyForMissingFile(): void
    {
        $loader = new CustomRuleLoader();

        /* Le repertoire temporaire ne contient pas de fichier de regles */
        $rules = $loader->load($this->tempDir);

        self::assertSame([], $rules, 'Un repertoire sans fichier de regles devrait retourner un tableau vide.');
    }

    /**
     * Verifie que load parse correctement un fichier YAML valide.
     * Un fichier contenant des regles bien formees doit retourner des objets CustomRule.
     */
    #[Test]
    public function testLoadParsesValidRules(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "test-deprecated-class"
      pattern: "App\\Legacy\\OldService"
      type: "php_class_usage"
      severity: "breaking"
      category: "deprecation"
      message: "La classe OldService est supprimee dans Sylius 2.x"
      suggestion: "Utiliser App\\Service\\NewService a la place"
      estimated_minutes: 30
YAML);

        $loader = new CustomRuleLoader();
        $rules = $loader->load($this->tempDir);

        self::assertCount(1, $rules, 'Une regle valide devrait etre chargee.');
        self::assertSame('test-deprecated-class', $rules[0]->name);
        self::assertSame('php_class_usage', $rules[0]->type);
        self::assertSame('breaking', $rules[0]->severity);
        self::assertSame('deprecation', $rules[0]->category);
        self::assertSame(30, $rules[0]->estimatedMinutes);
    }

    /**
     * Verifie que load leve une exception quand des champs requis sont manquants.
     * Chaque regle doit definir name, pattern, type, severity, category, message et suggestion.
     */
    #[Test]
    public function testLoadValidatesRequiredFields(): void
    {
        /* Regle incomplete : le champ 'pattern' est manquant */
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "incomplete-rule"
      type: "php_class_usage"
      severity: "warning"
      category: "deprecation"
      message: "Message test"
      suggestion: "Suggestion test"
YAML);

        $loader = new CustomRuleLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pattern/');

        $loader->load($this->tempDir);
    }

    /**
     * Verifie que les objets retournes sont bien des instances de CustomRule.
     * Le chargeur doit convertir les donnees YAML en objets valeur CustomRule.
     */
    #[Test]
    public function testLoadCreatesCustomRuleObjects(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "rule-one"
      pattern: "OldClass"
      type: "php_class_usage"
      severity: "warning"
      category: "deprecation"
      message: "Classe depreciee"
      suggestion: "Utiliser NewClass"
    - name: "rule-two"
      pattern: "old_config_key"
      type: "yaml_key"
      severity: "suggestion"
      category: "grid"
      message: "Cle de configuration depreciee"
      suggestion: "Utiliser new_config_key"
YAML);

        $loader = new CustomRuleLoader();
        $rules = $loader->load($this->tempDir);

        self::assertCount(2, $rules);

        foreach ($rules as $rule) {
            self::assertInstanceOf(CustomRule::class, $rule, 'Chaque regle chargee doit etre une instance de CustomRule.');
        }
    }

    /**
     * Verifie que load leve une exception pour un fichier YAML invalide.
     * Un contenu YAML malformed doit generer une InvalidArgumentException.
     */
    #[Test]
    public function testLoadHandlesInvalidYaml(): void
    {
        /* Contenu YAML syntaxiquement invalide */
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "test"
      pattern: "test
      invalid: [unclosed bracket
    broken indentation
  yaml: {
YAML);

        $loader = new CustomRuleLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/syntaxe YAML/');

        $loader->load($this->tempDir);
    }

    /**
     * Verifie que load valide les types de regles autorises.
     * Un type invalide doit lever une InvalidArgumentException.
     */
    #[Test]
    public function testLoadValidatesRuleTypes(): void
    {
        $this->writeRulesFile(<<<'YAML'
rules:
    - name: "invalid-type-rule"
      pattern: "SomeClass"
      type: "invalid_type"
      severity: "warning"
      category: "deprecation"
      message: "Message test"
      suggestion: "Suggestion test"
YAML);

        $loader = new CustomRuleLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/type invalide/');

        $loader->load($this->tempDir);
    }
}
