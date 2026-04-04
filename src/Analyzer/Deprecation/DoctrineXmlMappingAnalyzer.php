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
 * Analyseur des mappings Doctrine en format XML.
 * Sylius 2.0 migre vers les attributs PHP pour les mappings Doctrine.
 * Les fichiers *.orm.xml dans les repertoires de configuration doivent etre convertis.
 */
final class DoctrineXmlMappingAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par entite avec mapping XML */
    private const MINUTES_PER_ENTITY = 120;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    public function getName(): string
    {
        return 'Doctrine XML Mapping';
    }

    public function supports(MigrationReport $report): bool
    {
        $projectPath = $report->getProjectPath();

        /* Verification des repertoires standards de mapping Doctrine */
        $dirs = $this->getMappingDirectories($projectPath);

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $finder = new Finder();
                $finder->files()->in($dir)->name('*.orm.xml');

                if ($finder->hasResults()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $entityCount = 0;

        /* Recherche des fichiers *.orm.xml dans tous les repertoires de mapping connus */
        $dirs = $this->getMappingDirectories($projectPath);
        $existingDirs = [];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $existingDirs[] = $dir;
            }
        }

        if ($existingDirs === []) {
            return;
        }

        /* Recherche recursive dans les repertoires existants */
        $this->scanForXmlMappings($report, $projectPath, $existingDirs, $entityCount);

        /* En plus, scanner src/ recursivement pour les sous-repertoires doctrine/ */
        $srcDir = $projectPath . '/src';
        if (is_dir($srcDir)) {
            $this->scanSrcForDoctrineDirs($report, $projectPath, $srcDir, $entityCount);
        }

        /* Resume global */
        if ($entityCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::WARNING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d fichier(s) de mapping Doctrine XML detecte(s)',
                    $entityCount,
                ),
                detail: 'Sylius 2.0 migre vers les attributs PHP pour les mappings Doctrine. '
                    . 'Les fichiers *.orm.xml doivent etre convertis en attributs PHP sur les entites.',
                suggestion: 'Convertir chaque fichier de mapping XML en attributs PHP '
                    . '(#[ORM\\Entity], #[ORM\\Column], etc.) directement dans les classes d\'entites.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $entityCount * self::MINUTES_PER_ENTITY,
            ));
        }
    }

    /**
     * Retourne la liste des repertoires standards de mapping Doctrine.
     *
     * @return list<string>
     */
    private function getMappingDirectories(string $projectPath): array
    {
        return [
            $projectPath . '/config/doctrine',
            $projectPath . '/src/Entity/Resources/config/doctrine',
            $projectPath . '/src/Resources/config/doctrine',
        ];
    }

    /**
     * Scanne les repertoires specifies pour les fichiers *.orm.xml.
     *
     * @param list<string> $dirs
     */
    private function scanForXmlMappings(
        MigrationReport $report,
        string $projectPath,
        array $dirs,
        int &$entityCount,
    ): void {
        $finder = new Finder();
        $finder->files()->in($dirs)->name('*.orm.xml');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $entityCount++;
            $entityName = str_replace('.orm.xml', '', $file->getFilename());

            $report->addIssue(new MigrationIssue(
                severity: Severity::WARNING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf('Mapping XML Doctrine pour l\'entite "%s"', $entityName),
                detail: sprintf(
                    'Le fichier %s definit un mapping Doctrine en XML pour l\'entite "%s". '
                    . 'Sylius 2.0 utilise les attributs PHP pour les mappings.',
                    str_replace($projectPath . '/', '', $filePath),
                    $entityName,
                ),
                suggestion: sprintf(
                    'Convertir le mapping XML de "%s" en attributs PHP (#[ORM\\Entity], etc.) '
                    . 'dans la classe d\'entite correspondante.',
                    $entityName,
                ),
                file: $filePath,
                docUrl: self::DOC_URL,
            ));
        }
    }

    /**
     * Scanne le repertoire src/ recursivement pour trouver des sous-repertoires doctrine/.
     */
    private function scanSrcForDoctrineDirs(
        MigrationReport $report,
        string $projectPath,
        string $srcDir,
        int &$entityCount,
    ): void {
        /* Recherche de tous les repertoires nommes "doctrine" dans src/ */
        $dirFinder = new Finder();
        $dirFinder->directories()->in($srcDir)->name('doctrine');

        $doctrineDirs = [];
        foreach ($dirFinder as $dir) {
            $dirPath = $dir->getRealPath();
            if ($dirPath !== false) {
                $doctrineDirs[] = $dirPath;
            }
        }

        if ($doctrineDirs === []) {
            return;
        }

        $fileFinder = new Finder();
        $fileFinder->files()->in($doctrineDirs)->name('*.orm.xml');

        foreach ($fileFinder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            /* Eviter les doublons avec les repertoires deja scannes */
            $alreadyProcessed = false;
            foreach ($this->getMappingDirectories($projectPath) as $knownDir) {
                if (str_starts_with($filePath, $knownDir)) {
                    $alreadyProcessed = true;
                    break;
                }
            }

            if ($alreadyProcessed) {
                continue;
            }

            $entityCount++;
            $entityName = str_replace('.orm.xml', '', $file->getFilename());

            $report->addIssue(new MigrationIssue(
                severity: Severity::WARNING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf('Mapping XML Doctrine pour l\'entite "%s"', $entityName),
                detail: sprintf(
                    'Le fichier %s definit un mapping Doctrine en XML. '
                    . 'Sylius 2.0 utilise les attributs PHP pour les mappings.',
                    str_replace($projectPath . '/', '', $filePath),
                ),
                suggestion: sprintf(
                    'Convertir le mapping XML de "%s" en attributs PHP dans la classe d\'entite.',
                    $entityName,
                ),
                file: $filePath,
                docUrl: self::DOC_URL,
            ));
        }
    }
}
