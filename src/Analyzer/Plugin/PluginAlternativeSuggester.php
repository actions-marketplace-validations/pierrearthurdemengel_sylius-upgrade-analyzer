<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Plugin;

use Symfony\Component\Yaml\Yaml;

/**
 * Suggere des alternatives connues pour les plugins Sylius.
 * Utilise une base de donnees YAML maintenue dans le projet
 * pour proposer des remplacements lors de la migration vers Sylius 2.x.
 */
final class PluginAlternativeSuggester
{
    /** @var array<string, array{replacement: ?string, reason: string, migration_hours: int, doc_url: ?string}>|null */
    private ?array $alternatives = null;

    /**
     * @param string $dataFilePath Chemin absolu vers le fichier plugin-alternatives.yaml
     */
    public function __construct(
        private readonly string $dataFilePath,
    ) {
    }

    /**
     * Recherche une alternative connue pour un plugin donne.
     * Retourne un tableau d'informations si une alternative est connue, null sinon.
     *
     * @return ?array{replacement: ?string, reason: string, migration_hours: int, doc_url: ?string}
     */
    public function suggest(string $packageName): ?array
    {
        $alternatives = $this->loadAlternatives();

        $normalized = strtolower(trim($packageName));

        if (!isset($alternatives[$normalized])) {
            return null;
        }

        return $alternatives[$normalized];
    }

    /**
     * Retourne toutes les alternatives connues.
     *
     * @return array<string, array{replacement: ?string, reason: string, migration_hours: int, doc_url: ?string}>
     */
    public function getAllAlternatives(): array
    {
        return $this->loadAlternatives();
    }

    /**
     * Charge et met en cache les alternatives depuis le fichier YAML.
     * Les cles sont normalisees en minuscules pour une recherche insensible a la casse.
     *
     * @return array<string, array{replacement: ?string, reason: string, migration_hours: int, doc_url: ?string}>
     */
    private function loadAlternatives(): array
    {
        if ($this->alternatives !== null) {
            return $this->alternatives;
        }

        if (!file_exists($this->dataFilePath)) {
            $this->alternatives = [];

            return $this->alternatives;
        }

        $content = file_get_contents($this->dataFilePath);
        if ($content === false) {
            $this->alternatives = [];

            return $this->alternatives;
        }

        $parsed = Yaml::parse($content);
        if (!is_array($parsed) || !isset($parsed['alternatives']) || !is_array($parsed['alternatives'])) {
            $this->alternatives = [];

            return $this->alternatives;
        }

        $this->alternatives = [];

        foreach ($parsed['alternatives'] as $packageName => $data) {
            if (!is_string($packageName) || !is_array($data)) {
                continue;
            }

            $normalized = strtolower(trim($packageName));

            $this->alternatives[$normalized] = [
                'replacement' => isset($data['replacement']) && is_string($data['replacement']) ? $data['replacement'] : null,
                'reason' => isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : '',
                'migration_hours' => isset($data['migration_hours']) && is_int($data['migration_hours']) ? $data['migration_hours'] : 0,
                'doc_url' => isset($data['doc_url']) && is_string($data['doc_url']) ? $data['doc_url'] : null,
            ];
        }

        return $this->alternatives;
    }
}
