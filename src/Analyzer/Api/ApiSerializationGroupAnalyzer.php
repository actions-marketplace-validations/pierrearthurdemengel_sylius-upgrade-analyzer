<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Api;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Analyseur des groupes de serialisation API sans prefixe sylius:.
 * Sylius 2.0 impose le prefixe sylius: sur tous les groupes de serialisation API.
 * Cet analyseur detecte les groupes non prefixes dans les fichiers PHP et les configurations YAML/XML.
 */
final class ApiSerializationGroupAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par reference de groupe non prefixe */
    private const MINUTES_PER_GROUP = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /**
     * Pattern pour detecter les groupes dans les annotations entre guillemets.
     * Detecte les groupes admin: et shop: non prefixes dans les contextes normalization/denormalization.
     */
    private const ANNOTATION_GROUP_PATTERN = '/[\'"](?:admin|shop):[a-z_]+(?::[a-z_]+)*[\'"]/';

    /**
     * Pattern pour verifier que le groupe est correctement prefixe par sylius:.
     */
    private const SYLIUS_PREFIX_PATTERN = '/[\'"]sylius:(?:admin|shop):/';

    public function getName(): string
    {
        return 'API Serialization Group';
    }

    public function supports(MigrationReport $report): bool
    {
        $projectPath = $report->getProjectPath();

        /* Verification dans les fichiers PHP de src/ */
        $srcDir = $projectPath . '/src';
        if (is_dir($srcDir)) {
            $finder = new Finder();
            $finder->files()->in($srcDir)->name('*.php');

            foreach ($finder as $file) {
                $content = (string) file_get_contents((string) $file->getRealPath());
                if ($this->containsNonPrefixedGroups($content)) {
                    return true;
                }
            }
        }

        /* Verification dans les fichiers YAML de config/ */
        $configDir = $projectPath . '/config';
        if (is_dir($configDir)) {
            $finder = new Finder();
            $finder->files()->in($configDir)->name('*.yaml')->name('*.yml');

            foreach ($finder as $file) {
                $content = (string) file_get_contents((string) $file->getRealPath());
                if ($this->containsNonPrefixedGroupsInYaml($content)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $referenceCount = 0;

        /* Etape 1 : analyse des fichiers PHP dans src/ */
        $referenceCount += $this->analyzePhpFiles($report, $projectPath);

        /* Etape 2 : analyse des fichiers YAML dans config/ */
        $referenceCount += $this->analyzeYamlFiles($report, $projectPath);

        /* Etape 3 : ajout d'un probleme de synthese */
        if ($referenceCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::API,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d groupe(s) de serialisation sans prefixe sylius: detecte(s)',
                    $referenceCount,
                ),
                detail: 'Sylius 2.0 impose le prefixe sylius: sur tous les groupes de serialisation API. '
                    . 'Les groupes comme admin:product:read doivent devenir sylius:admin:product:read.',
                suggestion: 'Ajouter le prefixe sylius: a tous les groupes de serialisation '
                    . 'commencant par admin: ou shop: dans les attributs PHP et les configurations YAML.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $referenceCount * self::MINUTES_PER_GROUP,
            ));
        }
    }

    /**
     * Analyse les fichiers PHP dans src/ pour detecter les groupes de serialisation non prefixes.
     * Retourne le nombre de references trouvees.
     */
    private function analyzePhpFiles(MigrationReport $report, string $projectPath): int
    {
        $srcDir = $projectPath . '/src';
        if (!is_dir($srcDir)) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach ($lines as $index => $line) {
                /* Recherche des groupes admin: et shop: non prefixes */
                if (preg_match(self::ANNOTATION_GROUP_PATTERN, $line) !== 1) {
                    continue;
                }

                /* Verification que le groupe n'est pas deja prefixe par sylius: */
                if (preg_match(self::SYLIUS_PREFIX_PATTERN, $line) === 1) {
                    continue;
                }

                $count++;
                $lineNumber = $index + 1;

                /* Extraction du groupe detecte pour le message */
                $matches = [];
                preg_match(self::ANNOTATION_GROUP_PATTERN, $line, $matches);
                $groupName = isset($matches[0]) ? trim($matches[0], '\'"') : 'unknown';

                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::API,
                    analyzer: $this->getName(),
                    message: sprintf(
                        'Groupe de serialisation "%s" sans prefixe sylius: dans %s',
                        $groupName,
                        $file->getRelativePathname(),
                    ),
                    detail: sprintf(
                        'Le fichier %s ligne %d utilise le groupe "%s" sans le prefixe sylius:. '
                        . 'Sylius 2.0 impose le prefixe sylius: sur tous les groupes de serialisation API.',
                        $file->getRelativePathname(),
                        $lineNumber,
                        $groupName,
                    ),
                    suggestion: sprintf(
                        'Remplacer "%s" par "sylius:%s".',
                        $groupName,
                        $groupName,
                    ),
                    file: $filePath,
                    line: $lineNumber,
                    codeSnippet: trim($line),
                    docUrl: self::DOC_URL,
                ));
            }
        }

        return $count;
    }

    /**
     * Analyse les fichiers YAML dans config/ pour detecter les groupes de serialisation non prefixes.
     * Retourne le nombre de references trouvees.
     */
    private function analyzeYamlFiles(MigrationReport $report, string $projectPath): int
    {
        $configDir = $projectPath . '/config';
        if (!is_dir($configDir)) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($configDir)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            if (!$this->containsNonPrefixedGroupsInYaml($content)) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                /* Recherche des groupes admin: et shop: non prefixes dans le YAML */
                $yamlGroupPattern = '/[\'"](?:admin|shop):[a-z_]+(?::[a-z_]+)*[\'"]/';
                if (preg_match($yamlGroupPattern, $line) !== 1) {
                    continue;
                }

                /* Verification que le groupe n'est pas deja prefixe par sylius: */
                if (preg_match(self::SYLIUS_PREFIX_PATTERN, $line) === 1) {
                    continue;
                }

                $count++;
                $lineNumber = $index + 1;

                $matches = [];
                preg_match($yamlGroupPattern, $line, $matches);
                $groupName = isset($matches[0]) ? trim($matches[0], '\'"') : 'unknown';

                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::API,
                    analyzer: $this->getName(),
                    message: sprintf(
                        'Groupe de serialisation "%s" sans prefixe sylius: dans %s',
                        $groupName,
                        $file->getRelativePathname(),
                    ),
                    detail: sprintf(
                        'Le fichier de configuration %s ligne %d reference le groupe "%s" '
                        . 'sans le prefixe sylius:.',
                        $file->getRelativePathname(),
                        $lineNumber,
                        $groupName,
                    ),
                    suggestion: sprintf(
                        'Remplacer "%s" par "sylius:%s" dans la configuration.',
                        $groupName,
                        $groupName,
                    ),
                    file: $filePath,
                    line: $lineNumber,
                    codeSnippet: trim($line),
                    docUrl: self::DOC_URL,
                ));
            }
        }

        return $count;
    }

    /**
     * Verifie si le contenu PHP contient des groupes de serialisation non prefixes par sylius:.
     */
    private function containsNonPrefixedGroups(string $content): bool
    {
        /* Verifier d'abord la presence de groupes admin: ou shop: */
        if (preg_match(self::ANNOTATION_GROUP_PATTERN, $content) !== 1) {
            return false;
        }

        /* Verifier que ces groupes ne sont pas deja prefixes par sylius: */
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match(self::ANNOTATION_GROUP_PATTERN, $line) === 1
                && preg_match(self::SYLIUS_PREFIX_PATTERN, $line) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifie si le contenu YAML contient des groupes de serialisation non prefixes par sylius:.
     */
    private function containsNonPrefixedGroupsInYaml(string $content): bool
    {
        $yamlGroupPattern = '/[\'"](?:admin|shop):[a-z_]+(?::[a-z_]+)*[\'"]/';

        if (preg_match($yamlGroupPattern, $content) !== 1) {
            return false;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match($yamlGroupPattern, $line) === 1
                && preg_match(self::SYLIUS_PREFIX_PATTERN, $line) !== 1) {
                return true;
            }
        }

        return false;
    }
}
