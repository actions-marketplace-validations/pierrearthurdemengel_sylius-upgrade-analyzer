<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

/**
 * Represente un correctif genere automatiquement pour un probleme de migration.
 * Contient le contenu original, le contenu corrige et les metadonnees du correctif.
 */
final readonly class MigrationFix
{
    /**
     * @param FixConfidence $confidence      Niveau de confiance du correctif
     * @param string        $filePath        Chemin du fichier concerne
     * @param string        $originalContent Contenu original du fichier
     * @param string        $fixedContent    Contenu corrige du fichier
     * @param string        $description     Description du correctif applique
     */
    public function __construct(
        public FixConfidence $confidence,
        public string $filePath,
        public string $originalContent,
        public string $fixedContent,
        public string $description,
    ) {
    }
}
