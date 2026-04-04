<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

/**
 * Niveau de confiance d'un correctif automatique.
 * Indique la fiabilite estimee de la correction generee.
 */
enum FixConfidence: string
{
    /** Correctif fiable, applicable sans verification manuelle */
    case HIGH = 'high';

    /** Correctif probable, verification manuelle recommandee */
    case MEDIUM = 'medium';

    /** Correctif incertain, verification manuelle obligatoire */
    case LOW = 'low';
}
