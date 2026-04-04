<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Marketplace;

/**
 * Statut de compatibilite d'un plugin Sylius avec une version cible.
 */
enum PluginCompatibilityStatus: string
{
    /** Le plugin est entierement compatible avec la version cible */
    case COMPATIBLE = 'compatible';

    /** Le plugin est incompatible avec la version cible */
    case INCOMPATIBLE = 'incompatible';

    /** Le plugin est partiellement compatible, certaines fonctionnalites peuvent ne pas fonctionner */
    case PARTIALLY_COMPATIBLE = 'partially_compatible';

    /** La compatibilite du plugin n'a pas pu etre determinee */
    case UNKNOWN = 'unknown';

    /** Le plugin est abandonne et n'est plus maintenu */
    case ABANDONED = 'abandoned';
}
