<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Analyzer\Plugin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Plugin\PluginAlternativeSuggester;

/**
 * Tests unitaires pour le suggesteur d'alternatives de plugins Sylius.
 * Verifie le chargement du fichier YAML, la recherche d'alternatives
 * et le comportement en cas de plugin inconnu.
 */
final class PluginAlternativeSuggesterTest extends TestCase
{
    /** Chemin vers le fichier de donnees des alternatives de plugins */
    private const DATA_FILE_PATH = __DIR__ . '/../../../../data/plugin-alternatives.yaml';

    /**
     * Cree une instance du suggesteur pointant vers le vrai fichier de donnees.
     */
    private function createSuggester(): PluginAlternativeSuggester
    {
        return new PluginAlternativeSuggester(self::DATA_FILE_PATH);
    }

    /**
     * Verifie que suggest retourne une alternative pour un plugin connu.
     * Utilise bitbag/sylius-wishlist-plugin qui est present dans le fichier YAML.
     */
    #[Test]
    public function testSuggestReturnsAlternativeForKnownPlugin(): void
    {
        $suggester = $this->createSuggester();

        $result = $suggester->suggest('bitbag/sylius-wishlist-plugin');

        self::assertNotNull($result, 'Une alternative devrait exister pour bitbag/sylius-wishlist-plugin.');
        self::assertIsArray($result);
    }

    /**
     * Verifie que suggest retourne null pour un plugin inconnu.
     * Un plugin absent du fichier YAML ne doit pas avoir d'alternative.
     */
    #[Test]
    public function testSuggestReturnsNullForUnknownPlugin(): void
    {
        $suggester = $this->createSuggester();

        $result = $suggester->suggest('vendor/un-plugin-totalement-inconnu');

        self::assertNull($result, 'Aucune alternative ne devrait exister pour un plugin inconnu.');
    }

    /**
     * Verifie que getAllAlternatives retourne au moins 20 entrees.
     * Le fichier plugin-alternatives.yaml contient une base de donnees
     * suffisamment fournie pour depasser ce seuil.
     */
    #[Test]
    public function testGetAllAlternativesReturnsAllEntries(): void
    {
        $suggester = $this->createSuggester();

        $alternatives = $suggester->getAllAlternatives();

        self::assertIsArray($alternatives);
        self::assertGreaterThanOrEqual(20, count($alternatives), 'Le fichier YAML devrait contenir au moins 20 alternatives.');
    }

    /**
     * Verifie que la recherche d'alternative est insensible a la casse.
     * Les noms de paquets Composer sont normalises en minuscules.
     */
    #[Test]
    public function testSuggestIsCaseInsensitive(): void
    {
        $suggester = $this->createSuggester();

        /* Recherche avec des variations de casse */
        $resultLower = $suggester->suggest('bitbag/sylius-wishlist-plugin');
        $resultUpper = $suggester->suggest('BitBag/Sylius-Wishlist-Plugin');
        $resultMixed = $suggester->suggest('BITBAG/SYLIUS-WISHLIST-PLUGIN');

        self::assertNotNull($resultLower);
        self::assertNotNull($resultUpper, 'La recherche devrait etre insensible a la casse (majuscules).');
        self::assertNotNull($resultMixed, 'La recherche devrait etre insensible a la casse (tout en majuscules).');
        self::assertSame($resultLower, $resultUpper);
        self::assertSame($resultLower, $resultMixed);
    }

    /**
     * Verifie que l'alternative contient la cle 'replacement'.
     * Chaque entree du fichier YAML doit inclure un champ replacement
     * (pouvant etre null si aucun remplacement direct n'existe).
     */
    #[Test]
    public function testAlternativeIncludesReplacement(): void
    {
        $suggester = $this->createSuggester();

        $result = $suggester->suggest('bitbag/sylius-wishlist-plugin');

        self::assertNotNull($result);
        self::assertArrayHasKey('replacement', $result, 'L\'alternative devrait contenir la cle "replacement".');
        self::assertSame('sylius/wishlist-plugin', $result['replacement']);
    }

    /**
     * Verifie que l'alternative contient la cle 'migration_hours'.
     * Le nombre d'heures de migration est obligatoire pour chaque entree.
     */
    #[Test]
    public function testAlternativeIncludesMigrationHours(): void
    {
        $suggester = $this->createSuggester();

        $result = $suggester->suggest('bitbag/sylius-wishlist-plugin');

        self::assertNotNull($result);
        self::assertArrayHasKey('migration_hours', $result, 'L\'alternative devrait contenir la cle "migration_hours".');
        self::assertIsInt($result['migration_hours']);
        self::assertGreaterThan(0, $result['migration_hours'], 'Les heures de migration doivent etre positives.');
    }
}
