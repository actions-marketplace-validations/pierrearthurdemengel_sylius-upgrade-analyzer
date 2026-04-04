<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Analyseur des paquets deprecies ou supprimes dans Sylius 2.0.
 * Certains paquets tiers etaient integres au coeur de Sylius 1.x mais sont retires
 * ou remplaces dans la version 2.0. Cet analyseur verifie leur presence dans
 * la section require de composer.json.
 */
final class DeprecatedBundlePackageAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par paquet deprecie */
    private const MINUTES_PER_PACKAGE = 60;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /**
     * Paquets deprecies ou supprimes avec leur raison.
     *
     * @var array<string, string>
     */
    private const DEPRECATED_PACKAGES = [
        'friendsofsymfony/rest-bundle' => 'Removed in Sylius 2.0, API is handled by API Platform',
        'jms/serializer-bundle' => 'Removed in Sylius 2.0, use Symfony Serializer',
        'willdurand/hateoas-bundle' => 'Removed in Sylius 2.0',
        'bazinga/hateoas-bundle' => 'Removed in Sylius 2.0',
        'sylius/calendar' => 'Replaced by symfony/clock',
        'sylius-labs/polyfill-symfony-security' => 'No longer needed in Sylius 2.0',
        'stripe/stripe-php' => 'Stripe gateway removed from Sylius 2.0 core, use a plugin',
    ];

    public function getName(): string
    {
        return 'Deprecated Bundle Package';
    }

    public function supports(MigrationReport $report): bool
    {
        $composerJsonPath = $report->getProjectPath() . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return false;
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return false;
        }

        $require = $composerData['require'] ?? [];
        if (!is_array($require)) {
            return false;
        }

        foreach (array_keys(self::DEPRECATED_PACKAGES) as $package) {
            if (isset($require[$package])) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $composerJsonPath = $projectPath . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return;
        }

        $require = $composerData['require'] ?? [];
        if (!is_array($require)) {
            return;
        }

        $packageCount = 0;

        foreach (self::DEPRECATED_PACKAGES as $package => $reason) {
            if (!isset($require[$package])) {
                continue;
            }

            $packageCount++;
            $version = $require[$package];

            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    'Paquet deprecie %s (%s) detecte dans composer.json',
                    $package,
                    is_string($version) ? $version : 'version inconnue',
                ),
                detail: sprintf(
                    'Le paquet %s est present dans la section require de composer.json. '
                    . 'Raison : %s.',
                    $package,
                    $reason,
                ),
                suggestion: sprintf(
                    'Retirer %s de composer.json et migrer vers l\'alternative recommandee.',
                    $package,
                ),
                file: $composerJsonPath,
                docUrl: self::DOC_URL,
                estimatedMinutes: self::MINUTES_PER_PACKAGE,
            ));
        }

        /* Resume global */
        if ($packageCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d paquet(s) deprecie(s) ou supprime(s) detecte(s) dans composer.json',
                    $packageCount,
                ),
                detail: 'Plusieurs paquets utilises dans le projet ont ete retires ou remplaces dans Sylius 2.0. '
                    . 'Ces dependances doivent etre supprimees ou migrees vers leurs alternatives.',
                suggestion: 'Consulter la documentation de migration Sylius 2.0 pour chaque paquet '
                    . 'et suivre les instructions de remplacement.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $packageCount * self::MINUTES_PER_PACKAGE,
            ));
        }
    }
}
