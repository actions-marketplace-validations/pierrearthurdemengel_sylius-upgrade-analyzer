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
 * Analyseur des imports de routing obsoletes dans Sylius 2.0.
 * Detecte les anciens chemins d'import de routes dans les fichiers YAML
 * sous config/routes/ et config/routing/ qui doivent etre mis a jour.
 */
final class RoutingImportAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par import de routing a corriger */
    private const MINUTES_PER_ROUTING_ISSUE = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /** @var array<string, string> Anciens chemins d'import et leur description ou remplacement */
    private const OLD_ROUTING_IMPORTS = [
        '@SyliusShopBundle/Resources/config/routing/payum.yml' => '@SyliusPayumBundle/Resources/config/routing/integrations/sylius_shop.yaml',
        'sylius_shop_payum' => 'Import path changed to @SyliusPayumBundle',
        '@SyliusPaymentBundle/Resources/config/routing/integrations/sylius.yaml' => 'New payment notify route, must be imported',
    ];

    /** Ancien parametre de route a remplacer */
    private const OLD_API_ROUTE_PARAM = '%sylius.security.new_api_route%';

    /** Nouveau parametre de route */
    private const NEW_API_ROUTE_PARAM = '%sylius.security.api_route%';

    public function getName(): string
    {
        return 'Routing Import';
    }

    public function supports(MigrationReport $report): bool
    {
        $projectPath = $report->getProjectPath();
        $routingDirs = $this->getRoutingDirs($projectPath);

        if ($routingDirs === []) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($routingDirs)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $content = (string) file_get_contents((string) $file->getRealPath());

            /* Verification des anciens imports de routing */
            foreach (self::OLD_ROUTING_IMPORTS as $oldImport => $description) {
                if (str_contains($content, $oldImport)) {
                    return true;
                }
            }

            /* Verification de l'ancien parametre de route API */
            if (str_contains($content, self::OLD_API_ROUTE_PARAM)) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $issueCount = 0;

        /* Etape 1 : detection des anciens imports de routing */
        $issueCount += $this->analyzeOldRoutingImports($report, $projectPath);

        /* Etape 2 : detection de l'ancien parametre de route API */
        $issueCount += $this->analyzeOldApiRouteParam($report, $projectPath);

        /* Etape 3 : ajout d'un probleme de synthese */
        if ($issueCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d import(s) de routing obsolete(s) detecte(s)',
                    $issueCount,
                ),
                detail: 'Certains chemins d\'import de routing ont change dans Sylius 2.0. '
                    . 'Les anciens chemins doivent etre mis a jour.',
                suggestion: 'Mettre a jour les imports de routing vers les nouveaux chemins Sylius 2.0.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $issueCount * self::MINUTES_PER_ROUTING_ISSUE,
            ));
        }
    }

    /**
     * Parcourt les fichiers de routing pour detecter les anciens chemins d'import.
     * Retourne le nombre de problemes detectes.
     */
    private function analyzeOldRoutingImports(MigrationReport $report, string $projectPath): int
    {
        $routingDirs = $this->getRoutingDirs($projectPath);
        if ($routingDirs === []) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($routingDirs)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);

            foreach (self::OLD_ROUTING_IMPORTS as $oldImport => $replacement) {
                if (!str_contains($content, $oldImport)) {
                    continue;
                }

                $lines = explode("\n", $content);
                foreach ($lines as $index => $line) {
                    if (!str_contains($line, $oldImport)) {
                        continue;
                    }

                    $count++;
                    $lineNumber = $index + 1;

                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Import de routing obsolete "%s" dans %s',
                            $oldImport,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le fichier %s utilise l\'import "%s" ligne %d. '
                            . 'Ce chemin a change dans Sylius 2.0.',
                            $file->getRelativePathname(),
                            $oldImport,
                            $lineNumber,
                        ),
                        suggestion: sprintf(
                            'Remplacer l\'import par : %s.',
                            $replacement,
                        ),
                        file: $filePath,
                        line: $lineNumber,
                        codeSnippet: trim($line),
                        docUrl: self::DOC_URL,
                    ));

                    /* Une seule occurrence par import et par fichier suffit */
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Detecte l'utilisation de l'ancien parametre %sylius.security.new_api_route% dans les fichiers de routing.
     * Retourne le nombre d'occurrences trouvees.
     */
    private function analyzeOldApiRouteParam(MigrationReport $report, string $projectPath): int
    {
        $routingDirs = $this->getRoutingDirs($projectPath);
        if ($routingDirs === []) {
            return 0;
        }

        $count = 0;
        $finder = new Finder();
        $finder->files()->in($routingDirs)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            if (!str_contains($content, self::OLD_API_ROUTE_PARAM)) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                if (!str_contains($line, self::OLD_API_ROUTE_PARAM)) {
                    continue;
                }

                $count++;
                $lineNumber = $index + 1;

                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf(
                        'Parametre de route obsolete "%s" dans %s',
                        self::OLD_API_ROUTE_PARAM,
                        $file->getRelativePathname(),
                    ),
                    detail: sprintf(
                        'Le fichier %s utilise le parametre "%s" ligne %d. '
                        . 'Ce parametre a ete renomme dans Sylius 2.0.',
                        $file->getRelativePathname(),
                        self::OLD_API_ROUTE_PARAM,
                        $lineNumber,
                    ),
                    suggestion: sprintf(
                        'Remplacer "%s" par "%s".',
                        self::OLD_API_ROUTE_PARAM,
                        self::NEW_API_ROUTE_PARAM,
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
     * Retourne la liste des repertoires de routing existants dans le projet.
     *
     * @return list<string>
     */
    private function getRoutingDirs(string $projectPath): array
    {
        $dirs = [];

        $routesDir = $projectPath . '/config/routes';
        if (is_dir($routesDir)) {
            $dirs[] = $routesDir;
        }

        $routingDir = $projectPath . '/config/routing';
        if (is_dir($routingDir)) {
            $dirs[] = $routingDir;
        }

        return $dirs;
    }
}
