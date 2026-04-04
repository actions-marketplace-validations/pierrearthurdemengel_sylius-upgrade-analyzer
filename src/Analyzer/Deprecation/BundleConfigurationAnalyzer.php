<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;

/**
 * Analyseur de la configuration des bundles dans config/bundles.php.
 * Detecte les bundles obsoletes a supprimer et les bundles manquants a ajouter
 * pour la migration vers Sylius 2.0.
 */
final class BundleConfigurationAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par bundle a corriger */
    private const MINUTES_PER_BUNDLE = 30;

    /** Liste des bundles qui doivent etre supprimes dans Sylius 2.0 */
    private const BUNDLES_TO_REMOVE = [
        'Sylius\\Calendar\\SyliusCalendarBundle' => 'Remplace par symfony/clock. Supprimer ce bundle et utiliser ClockInterface.',
        'winzou\\Bundle\\StateMachineBundle\\winzouStateMachineBundle' => 'Remplace par Symfony Workflow. Supprimer ce bundle et migrer vers framework.workflows.',
        'Bazinga\\Bundle\\HateoasBundle\\BazingaHateoasBundle' => 'Plus necessaire avec API Platform. Supprimer ce bundle.',
        'JMS\\SerializerBundle\\JMSSerializerBundle' => 'Remplace par le serializer Symfony et API Platform. Supprimer ce bundle.',
        'FOS\\RestBundle\\FOSRestBundle' => 'Remplace par API Platform. Supprimer ce bundle et migrer les endpoints REST.',
        'ApiPlatform\\Core\\Bridge\\Symfony\\Bundle\\ApiPlatformBundle' => 'Ancien namespace API Platform. Remplacer par ApiPlatform\\Symfony\\Bundle\\ApiPlatformBundle.',
        'SyliusLabs\\Polyfill\\Symfony\\Security\\Bundle\\SyliusLabsPolyfillSymfonySecurityBundle' => 'Plus necessaire avec Symfony 6+. Supprimer ce polyfill de securite.',
    ];

    /** Liste des bundles qui doivent etre presents dans Sylius 2.0 */
    private const BUNDLES_REQUIRED = [
        'ApiPlatform\\Symfony\\Bundle\\ApiPlatformBundle' => 'Necessaire pour l\'API Sylius 2.0. Installer api-platform/symfony et enregistrer le bundle.',
        'Sylius\\TwigHooks\\SyliusTwigHooksBundle' => 'Remplace le systeme de template events de SyliusUiBundle. Installer sylius/twig-hooks et enregistrer le bundle.',
        'Symfony\\UX\\TwigComponent\\TwigComponentBundle' => 'Necessaire pour les composants Twig de Sylius 2.0. Installer symfony/ux-twig-component.',
        'Symfony\\UX\\StimulusBundle\\StimulusBundle' => 'Necessaire pour l\'integration Stimulus dans Sylius 2.0. Installer symfony/stimulus-bundle.',
        'Symfony\\UX\\LiveComponent\\LiveComponentBundle' => 'Necessaire pour les composants Live de Sylius 2.0. Installer symfony/ux-live-component.',
        'Symfony\\UX\\Autocomplete\\AutocompleteBundle' => 'Necessaire pour l\'autocompletion dans l\'admin Sylius 2.0. Installer symfony/ux-autocomplete.',
    ];

    public function getName(): string
    {
        return 'Bundle Configuration';
    }

    public function supports(MigrationReport $report): bool
    {
        $bundlesPhpPath = $report->getProjectPath() . '/config/bundles.php';

        return file_exists($bundlesPhpPath);
    }

    public function analyze(MigrationReport $report): void
    {
        $bundlesPhpPath = $report->getProjectPath() . '/config/bundles.php';
        if (!file_exists($bundlesPhpPath)) {
            return;
        }

        $content = (string) file_get_contents($bundlesPhpPath);

        /* Etape 1 : detection des bundles obsoletes a supprimer */
        $this->detectBundlesToRemove($report, $content, $bundlesPhpPath);

        /* Etape 2 : detection des bundles manquants a ajouter */
        $this->detectMissingBundles($report, $content, $bundlesPhpPath);
    }

    /**
     * Detecte les bundles obsoletes presents dans config/bundles.php.
     */
    private function detectBundlesToRemove(MigrationReport $report, string $content, string $filePath): void
    {
        foreach (self::BUNDLES_TO_REMOVE as $bundleClass => $suggestion) {
            /* Recherche du nom de classe du bundle dans le contenu du fichier */
            $escapedClass = str_replace('\\', '\\\\', $bundleClass);
            if (preg_match('/' . $escapedClass . '/', $content) === 1) {
                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf('Bundle obsolete detecte : %s', $this->getShortClassName($bundleClass)),
                    detail: sprintf(
                        'Le bundle %s est enregistre dans config/bundles.php mais il est obsolete dans Sylius 2.0.',
                        $bundleClass,
                    ),
                    suggestion: $suggestion,
                    file: $filePath,
                    estimatedMinutes: self::MINUTES_PER_BUNDLE,
                ));
            }
        }
    }

    /**
     * Detecte les bundles requis absents de config/bundles.php.
     */
    private function detectMissingBundles(MigrationReport $report, string $content, string $filePath): void
    {
        foreach (self::BUNDLES_REQUIRED as $bundleClass => $suggestion) {
            /* Recherche du nom de classe du bundle dans le contenu du fichier */
            $escapedClass = str_replace('\\', '\\\\', $bundleClass);
            if (preg_match('/' . $escapedClass . '/', $content) !== 1) {
                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf('Bundle manquant : %s', $this->getShortClassName($bundleClass)),
                    detail: sprintf(
                        'Le bundle %s n\'est pas enregistre dans config/bundles.php mais il est requis pour Sylius 2.0.',
                        $bundleClass,
                    ),
                    suggestion: $suggestion,
                    file: $filePath,
                    estimatedMinutes: self::MINUTES_PER_BUNDLE,
                ));
            }
        }
    }

    /**
     * Extrait le nom court d'une classe a partir de son FQCN.
     */
    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
