<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Interface pour les correctifs automatiques de migration.
 * Chaque implementation cible un type specifique de probleme detecte par les analyseurs.
 */
interface AutoFixInterface
{
    /** Identifiant unique du fixer */
    public function getName(): string;

    /** Determine si ce fixer peut corriger l'issue donnee */
    public function supports(MigrationIssue $issue): bool;

    /** Genere le correctif pour l'issue donnee. Retourne null si impossible */
    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix;
}
