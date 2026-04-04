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
 * Analyseur des noms de firewalls et parametres de securite deprecies.
 * Sylius 2.0 renomme les firewalls "new_api_*" en "api_*" et les parametres associes.
 * Cet analyseur detecte les anciennes references dans les fichiers de configuration YAML.
 */
final class SecurityFirewallAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par reference depreciee */
    private const MINUTES_PER_REFERENCE = 60;

    /**
     * Correspondances entre les anciens et nouveaux noms de firewalls.
     *
     * @var array<string, string>
     */
    private const FIREWALL_RENAMES = [
        'new_api_admin_user' => 'api_admin',
        'new_api_shop_user' => 'api_shop',
    ];

    /**
     * Correspondances entre les anciens et nouveaux noms de parametres de securite.
     *
     * @var array<string, string>
     */
    private const PARAMETER_RENAMES = [
        'sylius.security.new_api_route' => 'sylius.security.api_route',
        'sylius.security.new_api_regex' => 'sylius.security.api_regex',
        'sylius.security.new_api_admin_route' => 'sylius.security.api_admin_route',
        'sylius.security.new_api_admin_regex' => 'sylius.security.api_admin_regex',
        'sylius.security.new_api_shop_route' => 'sylius.security.api_shop_route',
        'sylius.security.new_api_shop_regex' => 'sylius.security.api_shop_regex',
        'sylius.security.new_api_user_account_route' => 'sylius.security.api_user_account_route',
        'sylius.security.new_api_user_account_regex' => 'sylius.security.api_user_account_regex',
    ];

    public function getName(): string
    {
        return 'Security Firewall';
    }

    public function supports(MigrationReport $report): bool
    {
        $configDir = $report->getProjectPath() . '/config';
        if (!is_dir($configDir)) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($configDir)->name('/^security\\.ya?ml$/');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);

            /* Recherche des anciens noms de firewalls */
            foreach (array_keys(self::FIREWALL_RENAMES) as $oldName) {
                if (str_contains($content, $oldName)) {
                    return true;
                }
            }

            /* Recherche des anciens noms de parametres */
            foreach (array_keys(self::PARAMETER_RENAMES) as $oldParam) {
                if (str_contains($content, $oldParam)) {
                    return true;
                }
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
        $finder->files()->in($configDir)->name('/^security\\.ya?ml$/');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            $lines = explode("\n", $content);

            /* Detection des anciens noms de firewalls */
            $this->detectFirewallRenames($report, $lines, $filePath);

            /* Detection des anciens noms de parametres */
            $this->detectParameterRenames($report, $lines, $filePath);
        }
    }

    /**
     * Detecte les noms de firewalls deprecies dans les lignes du fichier YAML.
     *
     * @param list<string> $lines
     */
    private function detectFirewallRenames(MigrationReport $report, array $lines, string $filePath): void
    {
        foreach ($lines as $index => $line) {
            foreach (self::FIREWALL_RENAMES as $oldName => $newName) {
                /* Recherche du nom de firewall comme cle YAML (avec les deux points) */
                if (preg_match('/^\s+' . preg_quote($oldName, '/') . '\s*:/', $line) === 1) {
                    $lineNumber = $index + 1;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf('Nom de firewall deprecie : %s', $oldName),
                        detail: sprintf(
                            'Le firewall "%s" en ligne %d doit etre renomme en "%s". '
                            . 'Les noms "new_api_*" sont deprecies dans Sylius 2.0.',
                            $oldName,
                            $lineNumber,
                            $newName,
                        ),
                        suggestion: sprintf(
                            'Renommer le firewall "%s" en "%s" dans la configuration de securite.',
                            $oldName,
                            $newName,
                        ),
                        file: $filePath,
                        line: $lineNumber,
                        estimatedMinutes: self::MINUTES_PER_REFERENCE,
                    ));
                }
            }
        }
    }

    /**
     * Detecte les parametres de securite deprecies dans les lignes du fichier YAML.
     *
     * @param list<string> $lines
     */
    private function detectParameterRenames(MigrationReport $report, array $lines, string $filePath): void
    {
        foreach ($lines as $index => $line) {
            foreach (self::PARAMETER_RENAMES as $oldParam => $newParam) {
                if (str_contains($line, $oldParam)) {
                    $lineNumber = $index + 1;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf('Parametre de securite deprecie : %s', $oldParam),
                        detail: sprintf(
                            'Le parametre "%s" en ligne %d doit etre renomme en "%s". '
                            . 'Les parametres "new_api_*" sont deprecies dans Sylius 2.0.',
                            $oldParam,
                            $lineNumber,
                            $newParam,
                        ),
                        suggestion: sprintf(
                            'Remplacer "%%' . '%s%%"' . ' par "%%' . '%s%%" dans la configuration de securite.',
                            $oldParam,
                            $newParam,
                        ),
                        file: $filePath,
                        line: $lineNumber,
                        estimatedMinutes: self::MINUTES_PER_REFERENCE,
                    ));
                }
            }
        }
    }
}
