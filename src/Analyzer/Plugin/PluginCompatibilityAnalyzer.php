<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Plugin;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\AddonsMarketplaceClient;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PackagistClient;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PluginCompatibility;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PluginCompatibilityStatus;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Analyseur de compatibilite des plugins Sylius.
 * Verifie chaque plugin installe contre Addons Marketplace et Packagist
 * pour determiner sa compatibilite avec la version cible de Sylius.
 */
final class PluginCompatibilityAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes pour un plugin incompatible (16h) */
    private const MINUTES_INCOMPATIBLE = 960;

    /** Estimation en minutes pour un plugin au statut inconnu (4h) */
    private const MINUTES_UNKNOWN = 240;

    /** Estimation en minutes pour un plugin compatible (0.5h) */
    private const MINUTES_COMPATIBLE = 30;

    /** Estimation en minutes pour un plugin partiellement compatible (4h) */
    private const MINUTES_PARTIALLY_COMPATIBLE = 240;

    /** Estimation en minutes pour un plugin abandonne (8h) */
    private const MINUTES_ABANDONED = 480;

    /** URL de la documentation sur les plugins Sylius 2.x */
    private const DOC_URL = 'https://docs.sylius.com/en/latest/book/plugins/guide.html';

    public function __construct(
        private readonly AddonsMarketplaceClient $addonsMarketplaceClient,
        private readonly PackagistClient $packagistClient,
        private readonly bool $noMarketplace = false,
    ) {
    }

    public function getName(): string
    {
        return 'Plugin Compatibility';
    }

    public function supports(MigrationReport $report): bool
    {
        $composerJsonPath = $report->getProjectPath() . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return false;
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return false;
        }

        $require = $composerData['require'] ?? [];
        if (!is_array($require)) {
            return false;
        }

        /* Recherche d'au moins un paquet lie a Sylius */
        foreach (array_keys($require) as $packageName) {
            if (!is_string($packageName)) {
                continue;
            }
            if ($this->isSyliusPlugin($packageName)) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $composerJsonPath = $projectPath . '/composer.json';

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return;
        }

        /* Etape 1 : extraction des plugins Sylius depuis composer.json */
        $plugins = $this->extractSyliusPlugins($composerData);
        if (count($plugins) === 0) {
            return;
        }

        /* Etape 2 : lecture des versions installees depuis composer.lock */
        $installedVersions = $this->readInstalledVersions($projectPath);

        /* Etape 3 : verification de la compatibilite de chaque plugin */
        $targetVersion = $report->getTargetVersion();

        foreach ($plugins as $packageName => $constraintVersion) {
            $currentVersion = $installedVersions[$packageName] ?? $constraintVersion;

            $compatibility = $this->checkPluginCompatibility($packageName, $targetVersion);

            /* Mise a jour de la version courante dans le resultat */
            $compatibility = new PluginCompatibility(
                packageName: $compatibility->packageName,
                currentVersion: $currentVersion,
                status: $compatibility->status,
                compatibleVersion: $compatibility->compatibleVersion,
                notes: $compatibility->notes,
            );

            $this->createIssueFromCompatibility($report, $compatibility);
        }
    }

    /**
     * Extrait les plugins Sylius depuis les donnees composer.json.
     * Un plugin Sylius est identifie par la presence de "sylius" dans le nom du vendor ou du paquet.
     *
     * @param array<mixed> $composerData Donnees decodees de composer.json
     * @return array<string, string> Tableau associatif nom_paquet => contrainte_version
     */
    private function extractSyliusPlugins(array $composerData): array
    {
        $plugins = [];
        $sections = ['require', 'require-dev'];

        foreach ($sections as $section) {
            $dependencies = $composerData[$section] ?? [];
            if (!is_array($dependencies)) {
                continue;
            }

            foreach ($dependencies as $packageName => $version) {
                if (!is_string($packageName) || !is_string($version)) {
                    continue;
                }

                if ($this->isSyliusPlugin($packageName)) {
                    $plugins[$packageName] = $version;
                }
            }
        }

        return $plugins;
    }

    /**
     * Determine si un nom de paquet correspond a un plugin Sylius.
     * Exclut les paquets du coeur Sylius (sylius/sylius, sylius/*-bundle, etc.).
     */
    private function isSyliusPlugin(string $packageName): bool
    {
        $parts = explode('/', $packageName);
        if (count($parts) !== 2) {
            return false;
        }

        [$vendor, $package] = $parts;

        /* Exclusion des paquets du coeur Sylius */
        if ($vendor === 'sylius' && !str_contains($package, 'plugin')) {
            return false;
        }

        /* Detection par la presence de "sylius" dans le vendor ou le package */
        $lowerVendor = strtolower($vendor);
        $lowerPackage = strtolower($package);

        return str_contains($lowerVendor, 'sylius') || str_contains($lowerPackage, 'sylius');
    }

    /**
     * Lit les versions installees depuis composer.lock.
     *
     * @return array<string, string> Tableau associatif nom_paquet => version_installee
     */
    private function readInstalledVersions(string $projectPath): array
    {
        $lockPath = $projectPath . '/composer.lock';
        if (!file_exists($lockPath)) {
            return [];
        }

        $lockData = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($lockData)) {
            return [];
        }

        $versions = [];
        $sections = ['packages', 'packages-dev'];

        foreach ($sections as $section) {
            $packages = $lockData[$section] ?? [];
            if (!is_array($packages)) {
                continue;
            }

            foreach ($packages as $packageData) {
                if (!is_array($packageData)) {
                    continue;
                }

                $name = $packageData['name'] ?? null;
                $version = $packageData['version'] ?? null;

                if (is_string($name) && is_string($version)) {
                    $versions[$name] = ltrim($version, 'v');
                }
            }
        }

        return $versions;
    }

    /**
     * Verifie la compatibilite d'un plugin en utilisant les clients disponibles.
     * Si noMarketplace est actif, retourne directement un statut UNKNOWN.
     */
    private function checkPluginCompatibility(string $packageName, string $targetVersion): PluginCompatibility
    {
        if ($this->noMarketplace) {
            return new PluginCompatibility(
                packageName: $packageName,
                currentVersion: '',
                status: PluginCompatibilityStatus::UNKNOWN,
                notes: 'Verification en ligne desactivee (mode hors-ligne)',
            );
        }

        /* Tentative via Addons Marketplace */
        $result = $this->addonsMarketplaceClient->checkCompatibility($packageName, $targetVersion);

        /* Si le resultat est concluant (pas UNKNOWN), on le retourne */
        if ($result->status !== PluginCompatibilityStatus::UNKNOWN) {
            return $result;
        }

        /* Repli sur Packagist */
        return $this->packagistClient->checkCompatibility($packageName, $targetVersion);
    }

    /**
     * Cree un probleme dans le rapport a partir du resultat de compatibilite.
     */
    private function createIssueFromCompatibility(MigrationReport $report, PluginCompatibility $compatibility): void
    {
        [$severity, $estimatedMinutes, $message, $detail, $suggestion] = match ($compatibility->status) {
            PluginCompatibilityStatus::INCOMPATIBLE => [
                Severity::BREAKING,
                self::MINUTES_INCOMPATIBLE,
                sprintf('Plugin incompatible detecte : %s', $compatibility->packageName),
                sprintf(
                    'Le plugin %s (version %s) est incompatible avec la version cible de Sylius. %s',
                    $compatibility->packageName,
                    $compatibility->currentVersion,
                    $compatibility->notes ?? '',
                ),
                sprintf(
                    'Rechercher une version compatible du plugin %s, contacter le mainteneur '
                    . 'ou envisager un remplacement. Si aucune alternative n\'existe, '
                    . 'la fonctionnalite devra etre reimplementee.',
                    $compatibility->packageName,
                ),
            ],
            PluginCompatibilityStatus::UNKNOWN => [
                Severity::WARNING,
                self::MINUTES_UNKNOWN,
                sprintf('Compatibilite inconnue pour le plugin : %s', $compatibility->packageName),
                sprintf(
                    'La compatibilite du plugin %s (version %s) avec la version cible n\'a pas pu etre determinee. %s',
                    $compatibility->packageName,
                    $compatibility->currentVersion,
                    $compatibility->notes ?? '',
                ),
                sprintf(
                    'Verifier manuellement la compatibilite du plugin %s avec Sylius cible. '
                    . 'Consulter le depot du plugin et tester dans un environnement de developpement.',
                    $compatibility->packageName,
                ),
            ],
            PluginCompatibilityStatus::COMPATIBLE => [
                Severity::SUGGESTION,
                self::MINUTES_COMPATIBLE,
                sprintf('Plugin compatible detecte : %s', $compatibility->packageName),
                sprintf(
                    'Le plugin %s (version %s) est compatible avec la version cible de Sylius. %s',
                    $compatibility->packageName,
                    $compatibility->currentVersion,
                    $compatibility->notes ?? '',
                ),
                $compatibility->compatibleVersion !== null
                    ? sprintf(
                        'Mettre a jour le plugin %s vers la version %s compatible.',
                        $compatibility->packageName,
                        $compatibility->compatibleVersion,
                    )
                    : sprintf('Verifier que la version actuelle du plugin %s fonctionne correctement.', $compatibility->packageName),
            ],
            PluginCompatibilityStatus::PARTIALLY_COMPATIBLE => [
                Severity::WARNING,
                self::MINUTES_PARTIALLY_COMPATIBLE,
                sprintf('Plugin partiellement compatible detecte : %s', $compatibility->packageName),
                sprintf(
                    'Le plugin %s (version %s) n\'est que partiellement compatible avec la version cible. %s',
                    $compatibility->packageName,
                    $compatibility->currentVersion,
                    $compatibility->notes ?? '',
                ),
                sprintf(
                    'Tester le plugin %s minutieusement apres la migration. '
                    . 'Certaines fonctionnalites peuvent necessiter des adaptations manuelles.',
                    $compatibility->packageName,
                ),
            ],
            PluginCompatibilityStatus::ABANDONED => [
                Severity::WARNING,
                self::MINUTES_ABANDONED,
                sprintf('Plugin abandonne detecte : %s', $compatibility->packageName),
                sprintf(
                    'Le plugin %s (version %s) est abandonne et ne recevra plus de mises a jour. %s',
                    $compatibility->packageName,
                    $compatibility->currentVersion,
                    $compatibility->notes ?? '',
                ),
                sprintf(
                    'Trouver un remplacement pour le plugin %s ou reimplementer '
                    . 'la fonctionnalite directement dans le projet.',
                    $compatibility->packageName,
                ),
            ],
        };

        $report->addIssue(new MigrationIssue(
            severity: $severity,
            category: Category::PLUGIN,
            analyzer: $this->getName(),
            message: $message,
            detail: $detail,
            suggestion: $suggestion,
            file: 'composer.json',
            docUrl: self::DOC_URL,
            estimatedMinutes: $estimatedMinutes,
        ));
    }
}
