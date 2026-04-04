<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Marketplace;

/**
 * Objet de valeur representant le resultat de verification de compatibilite d'un plugin.
 * Contient le nom du paquet, sa version actuelle, son statut et des informations complementaires.
 */
final readonly class PluginCompatibility
{
    /**
     * @param string                      $packageName       Nom complet du paquet (vendor/package)
     * @param string                      $currentVersion    Version actuellement installee
     * @param PluginCompatibilityStatus   $status            Statut de compatibilite determine
     * @param ?string                     $compatibleVersion Version compatible avec la cible, si disponible
     * @param ?string                     $notes             Notes complementaires sur la compatibilite
     */
    public function __construct(
        public string $packageName,
        public string $currentVersion,
        public PluginCompatibilityStatus $status,
        public ?string $compatibleVersion = null,
        public ?string $notes = null,
    ) {
    }
}
