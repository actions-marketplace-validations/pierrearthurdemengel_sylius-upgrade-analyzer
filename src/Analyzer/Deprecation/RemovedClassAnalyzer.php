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
 * Analyseur de l'utilisation de classes supprimees dans Sylius 2.0.
 * Detecte les instructions use faisant reference a des classes ou namespaces
 * supprimes dans la nouvelle version.
 */
final class RemovedClassAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par usage de classe supprimee */
    private const MINUTES_PER_USAGE = 60;

    /**
     * Prefixes de namespaces supprimes dans Sylius 2.0.
     * Toute classe dont le use commence par un de ces prefixes est signale.
     *
     * @var list<string>
     */
    private const REMOVED_PREFIXES = [
        'Sylius\\Bundle\\CoreBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\CurrencyBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\InventoryBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\LocaleBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\MoneyBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\OrderBundle\\Templating\\Helper\\',
        'Sylius\\Bundle\\UiBundle\\Registry\\',
        'Sylius\\Bundle\\UiBundle\\Renderer\\',
        'Sylius\\Bundle\\UiBundle\\ContextProvider\\',
        'Sylius\\Bundle\\UiBundle\\DataCollector\\',
        'Sylius\\Component\\Core\\Dashboard\\',
    ];

    /**
     * Classes exactes supprimees dans Sylius 2.0.
     *
     * @var list<string>
     */
    private const REMOVED_CLASSES = [
        'Sylius\\Bundle\\AdminBundle\\Controller\\NotificationController',
        'Sylius\\Bundle\\AdminBundle\\Controller\\Dashboard\\StatisticsController',
        'Sylius\\Bundle\\AdminBundle\\Provider\\StatisticsDataProvider',
        'Sylius\\Bundle\\UiBundle\\Twig\\TemplateEventExtension',
        'Sylius\\Bundle\\UiBundle\\Twig\\LegacySonataBlockExtension',
        'Sylius\\Bundle\\UiBundle\\Twig\\SortByExtension',
        'Sylius\\Bundle\\UiBundle\\Twig\\TestFormAttributeExtension',
        'Sylius\\Bundle\\UiBundle\\Twig\\TestHtmlAttributeExtension',
        'Sylius\\Bundle\\UiBundle\\Console\\Command\\DebugTemplateEventCommand',
        'Sylius\\Bundle\\UiBundle\\Storage\\FilterStorage',
        'Sylius\\Bundle\\UserBundle\\Security\\UserLogin',
        'Sylius\\Bundle\\UserBundle\\Security\\UserPasswordHasher',
        'Sylius\\Component\\User\\Security\\Generator\\UniquePinGenerator',
        'Sylius\\Bundle\\ProductBundle\\Controller\\ProductSlugController',
        'Sylius\\Bundle\\ProductBundle\\Controller\\ProductAttributeController',
        'Sylius\\Bundle\\ProductBundle\\Form\\Type\\ProductOptionChoiceType',
        'Sylius\\Bundle\\PayumBundle\\Controller\\PayumController',
    ];

    public function getName(): string
    {
        return 'Removed Class';
    }

    public function supports(MigrationReport $report): bool
    {
        $srcDir = $report->getProjectPath() . '/src';

        return is_dir($srcDir);
    }

    public function analyze(MigrationReport $report): void
    {
        $srcDir = $report->getProjectPath() . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);

            /* Extraction des instructions use du fichier */
            $this->analyzeUseStatements($report, $content, $filePath, $file->getRelativePathname());
        }
    }

    /**
     * Analyse les instructions use d'un fichier PHP pour detecter les classes supprimees.
     */
    private function analyzeUseStatements(
        MigrationReport $report,
        string $content,
        string $filePath,
        string $relativePath,
    ): void {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            /* Recherche des instructions use */
            if (preg_match('/^\s*use\s+(.+?)[\s;]/', $line, $matches) !== 1) {
                continue;
            }

            $usedClass = trim($matches[1]);

            /* Verification contre les prefixes de namespaces supprimes */
            foreach (self::REMOVED_PREFIXES as $prefix) {
                if (str_starts_with($usedClass, $prefix)) {
                    $this->reportRemovedClass($report, $usedClass, $filePath, $relativePath, $index + 1);
                    /* Passer a la ligne suivante apres le premier match pour eviter les doublons */
                    continue 2;
                }
            }

            /* Verification contre les classes exactes supprimees */
            foreach (self::REMOVED_CLASSES as $removedClass) {
                if ($usedClass === $removedClass) {
                    $this->reportRemovedClass($report, $usedClass, $filePath, $relativePath, $index + 1);
                    break;
                }
            }
        }
    }

    /**
     * Ajoute un probleme au rapport pour une classe supprimee detectee.
     */
    private function reportRemovedClass(
        MigrationReport $report,
        string $className,
        string $filePath,
        string $relativePath,
        int $lineNumber,
    ): void {
        $report->addIssue(new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::DEPRECATION,
            analyzer: $this->getName(),
            message: sprintf('Utilisation de la classe supprimee %s', $this->getShortClassName($className)),
            detail: sprintf(
                'La classe %s importee en ligne %d de %s est supprimee dans Sylius 2.0.',
                $className,
                $lineNumber,
                $relativePath,
            ),
            suggestion: $this->getSuggestionForClass($className),
            file: $filePath,
            line: $lineNumber,
            estimatedMinutes: self::MINUTES_PER_USAGE,
        ));
    }

    /**
     * Retourne la suggestion de remplacement pour une classe supprimee.
     */
    private function getSuggestionForClass(string $className): string
    {
        return match (true) {
            str_contains($className, 'Templating\\Helper')
                => 'Les helpers de template Twig sont supprimes. Utiliser les services Twig natifs '
                    . 'ou les fonctions Twig de Sylius 2.0 a la place.',
            str_contains($className, 'Dashboard') || str_contains($className, 'Statistics')
                => 'Les classes de dashboard et statistiques sont supprimees. '
                    . 'Implementer un mecanisme personnalise ou utiliser les nouveaux composants Sylius 2.0.',
            str_contains($className, 'UiBundle\\Registry') || str_contains($className, 'UiBundle\\Renderer')
                || str_contains($className, 'ContextProvider')
                => 'Le systeme de template blocks de SyliusUiBundle est remplace par Twig Hooks. '
                    . 'Migrer vers sylius/twig-hooks et Symfony UX.',
            str_contains($className, 'TemplateEventExtension') || str_contains($className, 'SonataBlock')
                => 'Les extensions Twig de template events sont supprimees. '
                    . 'Utiliser le systeme de Twig Hooks de Sylius 2.0.',
            str_contains($className, 'SortByExtension') || str_contains($className, 'TestFormAttribute')
                || str_contains($className, 'TestHtmlAttribute')
                => 'Cette extension Twig est supprimee dans Sylius 2.0. '
                    . 'Verifier si un equivalent existe dans les nouveaux composants.',
            str_contains($className, 'DebugTemplateEventCommand')
                => 'La commande de debug des template events est supprimee. '
                    . 'Utiliser les outils de debug de Twig Hooks a la place.',
            str_contains($className, 'FilterStorage') || str_contains($className, 'DataCollector')
                => 'Cette classe utilitaire de SyliusUiBundle est supprimee. '
                    . 'Implementer un equivalent personnalise si necessaire.',
            str_contains($className, 'UserLogin') || str_contains($className, 'UserPasswordHasher')
                => 'Utiliser les composants de securite Symfony natifs a la place. '
                    . 'Security\\UserLogin est remplace par le systeme d\'authentification Symfony.',
            str_contains($className, 'UniquePinGenerator')
                => 'Le generateur de codes PIN est supprime. '
                    . 'Implementer un generateur personnalise si necessaire.',
            str_contains($className, 'ProductSlugController') || str_contains($className, 'ProductAttributeController')
                => 'Ce controleur est supprime dans Sylius 2.0. '
                    . 'Utiliser les endpoints API Platform ou creer un controleur personnalise.',
            str_contains($className, 'ProductOptionChoiceType')
                => 'Ce type de formulaire est supprime. '
                    . 'Utiliser les types de formulaire Sylius 2.0 equivalents.',
            str_contains($className, 'PayumController')
                => 'Le controleur Payum est supprime. '
                    . 'Migrer vers le nouveau systeme de paiement de Sylius 2.0.',
            str_contains($className, 'NotificationController')
                => 'Le controleur de notifications admin est supprime. '
                    . 'Implementer un systeme de notifications personnalise si necessaire.',
            default => 'Cette classe est supprimee dans Sylius 2.0. '
                . 'Consulter la documentation de migration pour trouver un equivalent.',
        };
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
