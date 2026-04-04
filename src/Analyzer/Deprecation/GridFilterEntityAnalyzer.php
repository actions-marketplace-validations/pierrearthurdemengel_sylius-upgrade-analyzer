<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Analyseur des filtres de grille de type entity.
 * Detecte dans les fichiers YAML de configuration :
 * - Le type de filtre `entities` (doit etre `entity` dans Sylius 2.0)
 * - L'option `field:` au singulier (doit etre `fields:` au pluriel)
 * Ces changements de nomenclature sont requis pour la compatibilite Sylius 2.0.
 */
final class GridFilterEntityAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par filtre a corriger */
    private const MINUTES_PER_FILTER = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /** Motif de detection du type de filtre obsolete `entities` */
    private const ENTITIES_TYPE_PATTERN = 'type: entities';

    /** Expression reguliere pour detecter l'option `field:` au singulier dans un bloc de filtre */
    private const FIELD_SINGULAR_REGEX = '/^\s+field:\s/m';

    public function getName(): string
    {
        return 'Grid Filter Entity';
    }

    public function supports(MigrationReport $report): bool
    {
        $configDir = $report->getProjectPath() . '/config';
        if (!is_dir($configDir)) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($configDir)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $content = $file->getContents();

            if (str_contains($content, self::ENTITIES_TYPE_PATTERN)) {
                return true;
            }

            if (preg_match(self::FIELD_SINGULAR_REGEX, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $configDir = $report->getProjectPath() . '/config';
        if (!is_dir($configDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($configDir)->name('*.yaml')->name('*.yml');

        $totalFilters = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = $file->getContents();
            $lines = explode("\n", $content);
            $relativePath = 'config/' . $file->getRelativePathname();

            /* Detection du type `entities` obsolete */
            $totalFilters += $this->detectEntitiesType(
                $report,
                $lines,
                $filePath,
                $relativePath,
            );

            /* Detection de l'option `field:` au singulier */
            $totalFilters += $this->detectFieldSingular(
                $report,
                $lines,
                $filePath,
                $relativePath,
            );
        }

        /* Resume global */
        if ($totalFilters > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::GRID,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d filtre(s) de grille necessitant une correction detecte(s)',
                    $totalFilters,
                ),
                detail: 'Le projet contient des filtres de grille utilisant l\'ancien format '
                    . 'de Sylius 1.x. Le type `entities` doit etre remplace par `entity` '
                    . 'et l\'option `field:` doit etre remplacee par `fields:` (tableau).',
                suggestion: 'Remplacer `type: entities` par `type: entity` et '
                    . '`field: nom` par `fields: [nom]` dans tous les filtres de grille.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $totalFilters * self::MINUTES_PER_FILTER,
            ));
        }
    }

    /**
     * Detecte les occurrences du type de filtre `entities` dans les lignes d'un fichier.
     * Retourne le nombre d'occurrences trouvees.
     *
     * @param list<string> $lines Lignes du fichier
     */
    private function detectEntitiesType(
        MigrationReport $report,
        array $lines,
        string $filePath,
        string $relativePath,
    ): int {
        $count = 0;

        foreach ($lines as $index => $line) {
            if (!str_contains($line, self::ENTITIES_TYPE_PATTERN)) {
                continue;
            }

            $count++;
            $lineNumber = $index + 1;

            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::GRID,
                analyzer: $this->getName(),
                message: sprintf(
                    'Type de filtre `entities` obsolete dans %s ligne %d',
                    $relativePath,
                    $lineNumber,
                ),
                detail: sprintf(
                    'Le fichier %s utilise le type de filtre `entities` a la ligne %d. '
                    . 'Ce type est obsolete dans Sylius 2.0 et doit etre remplace par `entity`.',
                    $relativePath,
                    $lineNumber,
                ),
                suggestion: 'Remplacer `type: entities` par `type: entity`.',
                file: $filePath,
                line: $lineNumber,
                codeSnippet: trim($line),
                docUrl: self::DOC_URL,
            ));
        }

        return $count;
    }

    /**
     * Detecte les occurrences de l'option `field:` au singulier dans les lignes d'un fichier.
     * Retourne le nombre d'occurrences trouvees.
     *
     * @param list<string> $lines Lignes du fichier
     */
    private function detectFieldSingular(
        MigrationReport $report,
        array $lines,
        string $filePath,
        string $relativePath,
    ): int {
        $count = 0;

        foreach ($lines as $index => $line) {
            $trimmed = ltrim($line);

            /* Verification que la ligne commence par `field:` (option de filtre) */
            if (!str_starts_with($trimmed, 'field:')) {
                continue;
            }

            /* Exclusion de `fields:` au pluriel (deja correct) */
            if (str_starts_with($trimmed, 'fields:')) {
                continue;
            }

            $count++;
            $lineNumber = $index + 1;

            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::GRID,
                analyzer: $this->getName(),
                message: sprintf(
                    'Option `field:` au singulier dans %s ligne %d',
                    $relativePath,
                    $lineNumber,
                ),
                detail: sprintf(
                    'Le fichier %s utilise l\'option `field:` au singulier a la ligne %d. '
                    . 'Dans Sylius 2.0, cette option est renommee `fields:` et accepte un tableau.',
                    $relativePath,
                    $lineNumber,
                ),
                suggestion: 'Remplacer `field: nom` par `fields: [nom]`.',
                file: $filePath,
                line: $lineNumber,
                codeSnippet: trim($line),
                docUrl: self::DOC_URL,
            ));
        }

        return $count;
    }
}
