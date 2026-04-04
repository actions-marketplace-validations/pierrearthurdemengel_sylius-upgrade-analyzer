<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Marketplace;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Sylius Addons Marketplace.
 * Verifie la compatibilite des plugins avec une version cible de Sylius
 * en interrogeant le registre officiel des addons.
 */
final class AddonsMarketplaceClient
{
    /** Delai d'attente maximal pour les requetes HTTP en secondes */
    private const TIMEOUT = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl = 'https://addons.sylius.com',
    ) {
    }

    /**
     * Verifie la compatibilite d'un plugin via l'API Sylius Addons.
     * En cas d'erreur reseau ou de reponse invalide, retourne un statut UNKNOWN.
     */
    public function checkCompatibility(string $packageName, string $targetSyliusVersion): PluginCompatibility
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/api/plugins', [
                'query' => [
                    'name' => $packageName,
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return new PluginCompatibility(
                    packageName: $packageName,
                    currentVersion: '',
                    status: PluginCompatibilityStatus::UNKNOWN,
                    notes: sprintf('L\'API Addons a retourne le code HTTP %d', $statusCode),
                );
            }

            $data = $response->toArray(false);

            return $this->parseResponse($packageName, $data, $targetSyliusVersion);
        } catch (\Throwable $e) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: sprintf('Erreur lors de la requete vers Addons Marketplace : %s', $e->getMessage()),
            );
        }
    }

    /**
     * Analyse la reponse JSON de l'API pour determiner le statut de compatibilite.
     *
     * @param array<mixed> $data Donnees JSON decodees de l'API
     */
    private function parseResponse(string $packageName, array $data, string $targetSyliusVersion): PluginCompatibility
    {
        /* Recherche du plugin dans les resultats */
        $plugins = $data['items'] ?? $data;
        if (!is_array($plugins)) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: 'Format de reponse inattendu de l\'API Addons',
            );
        }

        $pluginData = null;
        foreach ($plugins as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            $name = $plugin['composerName'] ?? $plugin['name'] ?? '';
            if ($name === $packageName) {
                $pluginData = $plugin;
                break;
            }
        }

        if ($pluginData === null) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: 'Plugin non trouve dans le registre Addons Marketplace',
            );
        }

        /* Verification de la compatibilite avec la version cible */
        $syliusVersions = $pluginData['syliusVersions'] ?? $pluginData['supported_versions'] ?? [];
        if (!is_array($syliusVersions)) {
            $syliusVersions = [];
        }

        $latestVersion = $pluginData['latestVersion'] ?? $pluginData['latest_version'] ?? '';
        $majorTarget = $this->extractMajorVersion($targetSyliusVersion);

        foreach ($syliusVersions as $supportedVersion) {
            if (!is_string($supportedVersion)) {
                continue;
            }
            $majorSupported = $this->extractMajorVersion($supportedVersion);
            if ($majorSupported === $majorTarget) {
                return new PluginCompatibility(
                    packageName: $packageName,
                    currentVersion: is_string($latestVersion) ? $latestVersion : '',
                    status: PluginCompatibilityStatus::COMPATIBLE,
                    compatibleVersion: is_string($latestVersion) ? $latestVersion : null,
                    notes: sprintf('Compatible avec Sylius %s selon Addons Marketplace', $supportedVersion),
                );
            }
        }

        /* Si aucune version compatible n'est trouvee */
        return new PluginCompatibility(
            packageName: $packageName,
            currentVersion: is_string($latestVersion) ? $latestVersion : '',
            status: PluginCompatibilityStatus::INCOMPATIBLE,
            notes: sprintf(
                'Aucune version compatible avec Sylius %s trouvee dans Addons Marketplace. Versions supportees : %s',
                $targetSyliusVersion,
                implode(', ', array_filter($syliusVersions, 'is_string')) ?: 'aucune',
            ),
        );
    }

    /**
     * Extrait la version majeure d'une chaine de version.
     * Par exemple : "2.0.1" retourne "2", "^2.0" retourne "2".
     */
    private function extractMajorVersion(string $version): string
    {
        $cleaned = ltrim($version, '^~><=v');
        $parts = explode('.', $cleaned);

        return $parts[0];
    }
}
