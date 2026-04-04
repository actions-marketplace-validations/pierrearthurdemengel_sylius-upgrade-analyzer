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
 * Analyseur des cles de configuration supprimees dans Sylius 2.0.
 * Detecte les cles de configuration YAML obsoletes dans le repertoire config/
 * qui ont ete supprimees ou rendues inutiles lors de la migration vers Sylius 2.0.
 */
final class RemovedConfigKeyAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par cle de configuration a corriger */
    private const MINUTES_PER_CONFIG_KEY = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /** @var array<string, string> Cles supprimees et leur description */
    private const REMOVED_CONFIG_KEYS = [
        'sylius_core.autoconfigure_with_attributes' => 'Removed in Sylius 2.0, autoconfiguration is now always enabled',
        'sylius_order.autoconfigure_with_attributes' => 'Removed in Sylius 2.0, autoconfiguration is now always enabled',
        'sylius_core.state_machine' => 'Removed in Sylius 2.0, use symfony/workflow directly',
        'sylius_inventory.checker' => 'Removed in Sylius 2.0',
        'sylius.mailer.templates' => 'Removed in Sylius 2.0, use Symfony Mailer templates',
        'sylius_api.legacy_error_handling' => 'Removed in Sylius 2.0',
        'sylius_api.serialization_groups.skip_adding_read_group' => 'Removed in Sylius 2.0',
        'sylius_api.serialization_groups.skip_adding_index_and_show_groups' => 'Removed in Sylius 2.0',
        'sylius.mongodb_odm.repository.class' => 'MongoDB ODM support removed in Sylius 2.0',
        'sylius.phpcr_odm.repository.class' => 'PHPCR ODM support removed in Sylius 2.0',
    ];

    public function getName(): string
    {
        return 'Removed Config Key';
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
            $content = (string) file_get_contents((string) $file->getRealPath());
            foreach (self::REMOVED_CONFIG_KEYS as $key => $description) {
                /* Extraction de la cle racine pour la recherche rapide */
                $searchKey = $this->extractSearchToken($key);
                if (str_contains($content, $searchKey)) {
                    return true;
                }
            }

            /* Detection de la section resetting/pin dans sylius_user */
            if (str_contains($content, 'resetting:') && str_contains($content, 'pin:')) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $issueCount = 0;

        /* Etape 1 : detection des cles de configuration supprimees */
        $issueCount += $this->analyzeRemovedKeys($report, $projectPath);

        /* Etape 2 : detection de la configuration resetting/pin */
        $issueCount += $this->analyzeResettingPin($report, $projectPath);

        /* Etape 3 : ajout d'un probleme de synthese */
        if ($issueCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d cle(s) de configuration supprimee(s) detectee(s)',
                    $issueCount,
                ),
                detail: 'Certaines cles de configuration ont ete supprimees dans Sylius 2.0. '
                    . 'Elles doivent etre retirees ou remplacees par leurs equivalents.',
                suggestion: 'Supprimer les cles de configuration obsoletes et adapter la configuration '
                    . 'aux nouvelles conventions de Sylius 2.0.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $issueCount * self::MINUTES_PER_CONFIG_KEY,
            ));
        }
    }

    /**
     * Parcourt les fichiers YAML sous config/ pour detecter les cles de configuration supprimees.
     * Retourne le nombre de problemes detectes.
     */
    private function analyzeRemovedKeys(MigrationReport $report, string $projectPath): int
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

            foreach (self::REMOVED_CONFIG_KEYS as $key => $description) {
                $searchKey = $this->extractSearchToken($key);
                if (!str_contains($content, $searchKey)) {
                    continue;
                }

                /* Verification plus precise en parcourant les lignes */
                $lines = explode("\n", $content);
                foreach ($lines as $index => $line) {
                    if (!str_contains($line, $searchKey)) {
                        continue;
                    }

                    $count++;
                    $lineNumber = $index + 1;

                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Cle de configuration supprimee "%s" dans %s',
                            $key,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le fichier %s utilise la cle "%s" ligne %d. %s.',
                            $file->getRelativePathname(),
                            $key,
                            $lineNumber,
                            $description,
                        ),
                        suggestion: sprintf(
                            'Supprimer la cle "%s" de la configuration. %s.',
                            $key,
                            $description,
                        ),
                        file: $filePath,
                        line: $lineNumber,
                        codeSnippet: trim($line),
                        docUrl: self::DOC_URL,
                    ));

                    /* Une seule occurrence par cle et par fichier suffit */
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Detecte la configuration resetting/pin dans les sections sylius_user.
     * Cette configuration a ete supprimee dans Sylius 2.0.
     * Retourne le nombre d'occurrences trouvees.
     */
    private function analyzeResettingPin(MigrationReport $report, string $projectPath): int
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
            if (!str_contains($content, 'resetting:') || !str_contains($content, 'pin:')) {
                continue;
            }

            /* Verification contextuelle : la section resetting/pin dans un contexte sylius_user */
            if (!str_contains($content, 'sylius_user')) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                if (!str_contains($line, 'pin:')) {
                    continue;
                }

                $count++;
                $lineNumber = $index + 1;

                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf(
                        'Configuration resetting.pin detectee dans %s',
                        $file->getRelativePathname(),
                    ),
                    detail: sprintf(
                        'Le fichier %s configure resetting.pin ligne %d dans la section sylius_user. '
                        . 'Cette option a ete supprimee dans Sylius 2.0.',
                        $file->getRelativePathname(),
                        $lineNumber,
                    ),
                    suggestion: 'Supprimer la configuration resetting.pin de la section sylius_user.',
                    file: $filePath,
                    line: $lineNumber,
                    codeSnippet: trim($line),
                    docUrl: self::DOC_URL,
                ));

                break;
            }
        }

        return $count;
    }

    /**
     * Extrait le dernier segment de la cle pour la recherche dans le contenu du fichier.
     * Par exemple "sylius_core.autoconfigure_with_attributes" retourne "autoconfigure_with_attributes".
     */
    private function extractSearchToken(string $key): string
    {
        $parts = explode('.', $key);

        return end($parts);
    }
}
