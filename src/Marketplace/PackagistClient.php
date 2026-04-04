<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Marketplace;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Packagist.
 * Verifie la compatibilite des plugins Sylius en analysant les contraintes
 * de dependances declarees dans les versions publiees sur Packagist.
 */
final class PackagistClient
{
    /** Delai d'attente maximal pour les requetes HTTP en secondes */
    private const TIMEOUT = 5;

    /** URL de base de l'API Packagist */
    private const BASE_URL = 'https://packagist.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Verifie la compatibilite d'un plugin en analysant ses metadonnees Packagist.
     * En cas d'erreur reseau ou de reponse invalide, retourne un statut UNKNOWN.
     */
    public function checkCompatibility(string $packageName, string $targetSyliusVersion): PluginCompatibility
    {
        try {
            $url = sprintf('%s/packages/%s.json', self::BASE_URL, $packageName);
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return new PluginCompatibility(
                    packageName: $packageName,
                    currentVersion: '',
                    status: PluginCompatibilityStatus::UNKNOWN,
                    notes: sprintf('Packagist a retourne le code HTTP %d pour %s', $statusCode, $packageName),
                );
            }

            $data = $response->toArray(false);

            return $this->parseResponse($packageName, $data, $targetSyliusVersion);
        } catch (\Throwable $e) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: sprintf('Erreur lors de la requete vers Packagist : %s', $e->getMessage()),
            );
        }
    }

    /**
     * Analyse la reponse JSON de Packagist pour determiner le statut de compatibilite.
     *
     * @param array<mixed> $data Donnees JSON decodees de Packagist
     */
    private function parseResponse(string $packageName, array $data, string $targetSyliusVersion): PluginCompatibility
    {
        $packageData = $data['package'] ?? null;
        if (!is_array($packageData)) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: 'Format de reponse inattendu de Packagist',
            );
        }

        /* Verification du statut d'abandon */
        $abandoned = $packageData['abandoned'] ?? false;
        if ($abandoned !== false) {
            $replacement = is_string($abandoned) ? $abandoned : null;

            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::ABANDONED,
                notes: $replacement !== null
                    ? sprintf('Le paquet est abandonne. Remplacement suggere : %s', $replacement)
                    : 'Le paquet est abandonne et n\'a pas de remplacement suggere',
            );
        }

        /* Analyse des versions pour trouver une compatibilite avec Sylius cible */
        $versions = $packageData['versions'] ?? [];
        if (!is_array($versions)) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: 'Aucune information de version disponible sur Packagist',
            );
        }

        $majorTarget = $this->extractMajorVersion($targetSyliusVersion);
        $compatibleVersion = null;

        /* Parcours des versions pour trouver une compatibilite */
        foreach ($versions as $versionName => $versionData) {
            if (!is_array($versionData) || !is_string($versionName)) {
                continue;
            }

            /* Ignorer les branches de developpement */
            if (str_starts_with($versionName, 'dev-')) {
                continue;
            }

            $require = $versionData['require'] ?? [];
            if (!is_array($require)) {
                continue;
            }

            /* Recherche de la dependance sylius/sylius dans les exigences */
            foreach ($require as $depName => $depConstraint) {
                if (!is_string($depName) || !is_string($depConstraint)) {
                    continue;
                }

                if (!$this->isSyliusDependency($depName)) {
                    continue;
                }

                if ($this->constraintMatchesMajor($depConstraint, $majorTarget)) {
                    $compatibleVersion = $versionName;
                    break 2;
                }
            }
        }

        if ($compatibleVersion !== null) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::COMPATIBLE,
                compatibleVersion: $compatibleVersion,
                notes: sprintf(
                    'La version %s est compatible avec Sylius %s selon Packagist',
                    $compatibleVersion,
                    $targetSyliusVersion,
                ),
            );
        }

        /* Recherche de compatibilite partielle (branches de developpement) */
        foreach ($versions as $versionName => $versionData) {
            if (!is_array($versionData) || !is_string($versionName)) {
                continue;
            }

            if (!str_starts_with($versionName, 'dev-')) {
                continue;
            }

            $require = $versionData['require'] ?? [];
            if (!is_array($require)) {
                continue;
            }

            foreach ($require as $depName => $depConstraint) {
                if (!is_string($depName) || !is_string($depConstraint)) {
                    continue;
                }

                if (!$this->isSyliusDependency($depName)) {
                    continue;
                }

                if ($this->constraintMatchesMajor($depConstraint, $majorTarget)) {
                    return new PluginCompatibility(
                        packageName: $packageName,
                        currentVersion: '',
                        status: PluginCompatibilityStatus::PARTIALLY_COMPATIBLE,
                        compatibleVersion: $versionName,
                        notes: sprintf(
                            'Seule la branche de developpement %s est compatible avec Sylius %s',
                            $versionName,
                            $targetSyliusVersion,
                        ),
                    );
                }
            }
        }

        return new PluginCompatibility(
            packageName: $packageName,
            currentVersion: '',
            status: PluginCompatibilityStatus::INCOMPATIBLE,
            notes: sprintf(
                'Aucune version compatible avec Sylius %s trouvee sur Packagist',
                $targetSyliusVersion,
            ),
        );
    }

    /**
     * Verifie si le nom de dependance correspond a un paquet Sylius principal.
     */
    private function isSyliusDependency(string $name): bool
    {
        return $name === 'sylius/sylius'
            || $name === 'sylius/core-bundle'
            || $name === 'sylius/resource-bundle'
            || $name === 'sylius/grid-bundle';
    }

    /**
     * Verifie si une contrainte de version Composer est compatible avec une version majeure.
     * Par exemple : "^2.0" est compatible avec la majeure "2".
     */
    private function constraintMatchesMajor(string $constraint, string $majorTarget): bool
    {
        /* Decoupe des contraintes multiples (|| ou |) */
        $alternatives = preg_split('/\s*\|\|?\s*/', $constraint);
        if ($alternatives === false) {
            return false;
        }

        foreach ($alternatives as $alternative) {
            $alternative = trim($alternative);
            if ($alternative === '') {
                continue;
            }

            /* Decoupe des contraintes combinees (espaces = AND) */
            $parts = preg_split('/\s+/', $alternative);
            if ($parts === false) {
                continue;
            }

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $cleaned = ltrim($part, '^~><=!v');
                $partMajor = explode('.', $cleaned)[0];

                if ($partMajor === $majorTarget) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extrait la version majeure d'une chaine de version.
     */
    private function extractMajorVersion(string $version): string
    {
        $cleaned = ltrim($version, '^~><=v');
        $parts = explode('.', $cleaned);

        return $parts[0];
    }
}
