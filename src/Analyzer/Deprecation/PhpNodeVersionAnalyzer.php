<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Analyseur des versions PHP, Node.js et Symfony.
 * Detecte dans composer.json :
 * - Les contraintes PHP qui autorisent des versions inferieures a 8.2
 * - Les dependances Symfony 5.4 sans 6.4
 * Detecte dans package.json :
 * - Les contraintes Node.js qui autorisent des versions inferieures a 20
 * Sylius 2.0 exige PHP >= 8.2, Node.js >= 20 et Symfony >= 6.4.
 */
final class PhpNodeVersionAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par probleme de version */
    private const MINUTES_PER_VERSION_ISSUE = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /** Version minimale de PHP requise par Sylius 2.0 */
    private const MIN_PHP_VERSION = '8.2';

    /** Version minimale de Node.js requise par Sylius 2.0 */
    private const MIN_NODE_VERSION = 20;

    /**
     * Contraintes PHP qui autorisent des versions trop anciennes.
     *
     * @var list<string>
     */
    private const OLD_PHP_CONSTRAINTS = [
        '>=8.0',
        '>=8.1',
        '^8.0',
        '^8.1',
        '~8.0',
        '~8.1',
    ];

    /**
     * Expression reguliere pour detecter les dependances Symfony 5.4 uniquement.
     * Correspond a des versions comme ^5.4, ~5.4, >=5.4 sans presence de ^6 ou ^7.
     */
    private const SYMFONY_54_PATTERN = '/\^5\.4|\~5\.4|>=5\.4/';

    public function getName(): string
    {
        return 'PHP Node Version';
    }

    public function supports(MigrationReport $report): bool
    {
        $projectPath = $report->getProjectPath();

        return file_exists($projectPath . '/composer.json')
            || file_exists($projectPath . '/package.json');
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $totalIssues = 0;

        /* Etape 1 : analyse de la contrainte PHP dans composer.json */
        $totalIssues += $this->analyzePhpVersion($report, $projectPath);

        /* Etape 2 : analyse des dependances Symfony dans composer.json */
        $totalIssues += $this->analyzeSymfonyDependencies($report, $projectPath);

        /* Etape 3 : analyse de la version Node.js dans package.json */
        $totalIssues += $this->analyzeNodeVersion($report, $projectPath);

        /* Etape 4 : resume global */
        if ($totalIssues > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d probleme(s) de version detecte(s)',
                    $totalIssues,
                ),
                detail: 'Le projet a des contraintes de versions incompatibles avec Sylius 2.0. '
                    . 'Sylius 2.0 requiert PHP >= 8.2, Node.js >= 20 et Symfony >= 6.4.',
                suggestion: 'Mettre a jour les contraintes de versions dans composer.json '
                    . 'et package.json pour satisfaire les exigences de Sylius 2.0.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $totalIssues * self::MINUTES_PER_VERSION_ISSUE,
            ));
        }
    }

    /**
     * Analyse la contrainte PHP dans composer.json.
     * Retourne le nombre de problemes trouves (0 ou 1).
     */
    private function analyzePhpVersion(MigrationReport $report, string $projectPath): int
    {
        $composerData = $this->readJsonFile($projectPath . '/composer.json');
        if ($composerData === null) {
            return 0;
        }

        $phpConstraint = $composerData['require']['php'] ?? null;
        if (!is_string($phpConstraint)) {
            return 0;
        }

        /* Verification si la contrainte autorise des versions trop anciennes */
        $isOldConstraint = false;
        foreach (self::OLD_PHP_CONSTRAINTS as $oldConstraint) {
            if (str_contains($phpConstraint, $oldConstraint)) {
                $isOldConstraint = true;
                break;
            }
        }

        if (!$isOldConstraint) {
            return 0;
        }

        $report->addIssue(new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: $this->getName(),
            message: sprintf(
                'Contrainte PHP trop permissive : %s',
                $phpConstraint,
            ),
            detail: sprintf(
                'La contrainte PHP "%s" dans composer.json autorise des versions '
                . 'inferieures a %s. Sylius 2.0 requiert PHP >= %s.',
                $phpConstraint,
                self::MIN_PHP_VERSION,
                self::MIN_PHP_VERSION,
            ),
            suggestion: sprintf(
                'Modifier la contrainte PHP dans composer.json pour exiger au minimum '
                . 'PHP %s : "php": ">=%s".',
                self::MIN_PHP_VERSION,
                self::MIN_PHP_VERSION,
            ),
            file: $projectPath . '/composer.json',
            docUrl: self::DOC_URL,
        ));

        return 1;
    }

    /**
     * Analyse les dependances Symfony dans composer.json.
     * Detecte les dependances symfony/* avec ^5.4 sans ^6.4.
     * Retourne le nombre de dependances problematiques.
     */
    private function analyzeSymfonyDependencies(MigrationReport $report, string $projectPath): int
    {
        $composerData = $this->readJsonFile($projectPath . '/composer.json');
        if ($composerData === null) {
            return 0;
        }

        $require = $composerData['require'] ?? [];
        if (!is_array($require)) {
            return 0;
        }

        $count = 0;

        foreach ($require as $package => $version) {
            if (!is_string($package) || !is_string($version)) {
                continue;
            }

            /* Filtrage des paquets Symfony */
            if (!str_starts_with($package, 'symfony/')) {
                continue;
            }

            /* Verification que la contrainte contient ^5.4 sans ^6 ou ^7 */
            if (preg_match(self::SYMFONY_54_PATTERN, $version) !== 1) {
                continue;
            }

            /* Si la contrainte contient aussi ^6 ou ||, elle est probablement deja correcte */
            if (str_contains($version, '^6') || str_contains($version, '||')) {
                continue;
            }

            $count++;

            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    'Dependance Symfony 5.4 detectee : %s (%s)',
                    $package,
                    $version,
                ),
                detail: sprintf(
                    'Le paquet %s est contraint a la version %s dans composer.json. '
                    . 'Sylius 2.0 requiert Symfony >= 6.4. La dependance a Symfony 5.4 '
                    . 'doit etre mise a jour.',
                    $package,
                    $version,
                ),
                suggestion: sprintf(
                    'Mettre a jour la contrainte de %s vers "^6.4" ou "^6.4 || ^7.0" '
                    . 'dans composer.json.',
                    $package,
                ),
                file: $projectPath . '/composer.json',
                docUrl: self::DOC_URL,
            ));
        }

        return $count;
    }

    /**
     * Analyse la contrainte Node.js dans package.json.
     * Retourne le nombre de problemes trouves (0 ou 1).
     */
    private function analyzeNodeVersion(MigrationReport $report, string $projectPath): int
    {
        $packageData = $this->readJsonFile($projectPath . '/package.json');
        if ($packageData === null) {
            return 0;
        }

        $engines = $packageData['engines'] ?? null;
        if (!is_array($engines)) {
            return 0;
        }

        $nodeConstraint = $engines['node'] ?? null;
        if (!is_string($nodeConstraint)) {
            return 0;
        }

        /* Extraction de la version numerique depuis la contrainte */
        if (!$this->isNodeVersionTooOld($nodeConstraint)) {
            return 0;
        }

        $report->addIssue(new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: $this->getName(),
            message: sprintf(
                'Contrainte Node.js trop permissive : %s',
                $nodeConstraint,
            ),
            detail: sprintf(
                'La contrainte Node.js "%s" dans package.json autorise des versions '
                . 'inferieures a %d. Sylius 2.0 recommande Node.js >= %d.',
                $nodeConstraint,
                self::MIN_NODE_VERSION,
                self::MIN_NODE_VERSION,
            ),
            suggestion: sprintf(
                'Modifier la contrainte Node.js dans package.json pour exiger au minimum '
                . 'Node.js %d : "engines": { "node": ">=%d" }.',
                self::MIN_NODE_VERSION,
                self::MIN_NODE_VERSION,
            ),
            file: $projectPath . '/package.json',
            docUrl: self::DOC_URL,
        ));

        return 1;
    }

    /**
     * Verifie si la contrainte Node.js autorise des versions trop anciennes.
     * Retourne true si la version minimale est inferieure a MIN_NODE_VERSION.
     */
    private function isNodeVersionTooOld(string $constraint): bool
    {
        /* Extraction du numero de version majeure depuis la contrainte */
        if (preg_match('/(\d+)/', $constraint, $matches) !== 1) {
            return false;
        }

        $majorVersion = (int) $matches[1];

        /* Les contraintes >=X ou ^X autorisent la version X et superieures */
        return $majorVersion < self::MIN_NODE_VERSION;
    }

    /**
     * Lit et decode un fichier JSON.
     * Retourne null si le fichier n'existe pas ou n'est pas un JSON valide.
     *
     * @return array<string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
