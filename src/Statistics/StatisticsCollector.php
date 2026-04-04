<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Statistics;

use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Collecte et envoie des statistiques anonymes d'utilisation.
 * Les donnees collectees ne contiennent aucune information identifiante
 * et sont uniquement utilisees pour ameliorer l'outil.
 * L'envoi est conditionne par l'opt-in dans composer.json.
 */
final class StatisticsCollector
{
    /** URL du service de collecte de statistiques */
    private const COLLECT_URL = 'https://stats.sylius-upgrade-analyzer.dev/collect';

    /** Delai d'attente maximal pour l'envoi en secondes */
    private const TIMEOUT = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Construit les donnees statistiques anonymes a partir du rapport de migration.
     * Aucune information identifiante n'est incluse (pas de chemin, pas de nom de projet).
     *
     * @return array<string, mixed> Donnees statistiques anonymisees
     */
    public function collect(MigrationReport $report): array
    {
        /* Comptage des issues par severite */
        $breakingCount = count($report->getIssuesBySeverity(Severity::BREAKING));
        $warningCount = count($report->getIssuesBySeverity(Severity::WARNING));
        $suggestionCount = count($report->getIssuesBySeverity(Severity::SUGGESTION));

        /* Comptage des issues par categorie */
        $categoryCounts = [];
        foreach (Category::cases() as $category) {
            $count = count($report->getIssuesByCategory($category));
            if ($count > 0) {
                $categoryCounts[$category->value] = $count;
            }
        }

        /* Detection de la version PHP depuis le projet */
        $phpVersion = $this->detectPhpVersion($report->getProjectPath());

        return [
            'tool_version' => $this->getToolVersion(),
            'complexity' => $report->getComplexity()->value,
            'total_hours' => $report->getTotalEstimatedHours(),
            'total_issues' => count($report->getIssues()),
            'breaking_count' => $breakingCount,
            'warning_count' => $warningCount,
            'suggestion_count' => $suggestionCount,
            'category_counts' => $categoryCounts,
            'php_version' => $phpVersion,
            'sylius_version' => $report->getDetectedSyliusVersion(),
            'target_version' => $report->getTargetVersion(),
            'analysis_duration_seconds' => $this->computeDurationSeconds($report),
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    /**
     * Envoie les statistiques collectees au service de collecte.
     * L'envoi est silencieux : les erreurs sont ignorees pour ne pas
     * perturber le fonctionnement de l'outil.
     *
     * @param array<string, mixed> $data Donnees statistiques a envoyer
     */
    public function send(array $data): void
    {
        try {
            $this->httpClient->request('POST', self::COLLECT_URL, [
                'json' => $data,
                'timeout' => self::TIMEOUT,
            ]);
        } catch (\Throwable) {
            /* Erreur silencieuse : la collecte de statistiques ne doit jamais
             * bloquer le fonctionnement normal de l'outil */
        }
    }

    /**
     * Verifie si l'utilisateur a opte pour la collecte de statistiques.
     * L'opt-in est configure dans la section extra de composer.json :
     *
     *   "extra": {
     *       "sylius-upgrade-analyzer": {
     *           "telemetry": true
     *       }
     *   }
     */
    public function isOptedIn(string $projectPath): bool
    {
        $composerJsonPath = rtrim($projectPath, '/\\') . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return false;
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return false;
        }

        $composerData = json_decode($content, true);
        if (!is_array($composerData)) {
            return false;
        }

        $extra = $composerData['extra'] ?? [];
        if (!is_array($extra)) {
            return false;
        }

        $analyzerConfig = $extra['sylius-upgrade-analyzer'] ?? [];
        if (!is_array($analyzerConfig)) {
            return false;
        }

        $telemetry = $analyzerConfig['telemetry'] ?? false;

        return $telemetry === true;
    }

    /**
     * Detecte la version PHP requise depuis le composer.json du projet.
     * Retourne la contrainte brute telle que definie dans require.php.
     */
    private function detectPhpVersion(string $projectPath): ?string
    {
        $composerJsonPath = rtrim($projectPath, '/\\') . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return null;
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return null;
        }

        $composerData = json_decode($content, true);
        if (!is_array($composerData)) {
            return null;
        }

        $require = $composerData['require'] ?? [];
        if (!is_array($require)) {
            return null;
        }

        $phpConstraint = $require['php'] ?? null;

        return is_string($phpConstraint) ? $phpConstraint : null;
    }

    /**
     * Calcule la duree de l'analyse en secondes.
     * Retourne null si le rapport n'est pas encore termine.
     */
    private function computeDurationSeconds(MigrationReport $report): ?float
    {
        $completedAt = $report->getCompletedAt();
        if ($completedAt === null) {
            return null;
        }

        $startedAt = $report->getStartedAt();
        $diff = $completedAt->getTimestamp() - $startedAt->getTimestamp();

        /* Precision a la milliseconde via les microsecondes */
        $microDiff = (float) $completedAt->format('u') - (float) $startedAt->format('u');

        return round($diff + $microDiff / 1_000_000, 3);
    }

    /**
     * Retourne la version de l'outil depuis le composer.json du projet.
     * Retourne 'dev' si la version ne peut pas etre determinee.
     */
    private function getToolVersion(): string
    {
        /* Tentative de lecture via le fichier installed.json de Composer */
        $installedJsonPath = __DIR__ . '/../../vendor/composer/installed.json';

        if (!file_exists($installedJsonPath)) {
            return 'dev';
        }

        $content = file_get_contents($installedJsonPath);
        if ($content === false) {
            return 'dev';
        }

        $installed = json_decode($content, true);
        if (!is_array($installed)) {
            return 'dev';
        }

        /* Format Composer 2.x : les paquets sont dans la cle "packages" */
        $packages = $installed['packages'] ?? $installed;
        if (!is_array($packages)) {
            return 'dev';
        }

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? '';
            if ($name === 'pierre-arthur/sylius-upgrade-analyzer') {
                return $package['version'] ?? 'dev';
            }
        }

        return 'dev';
    }
}
