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
 * Analyseur des signatures de constructeurs modifiees.
 * Detecte les classes PHP qui etendent des classes Sylius dont le constructeur
 * a change dans Sylius 2.0. Ces classes necessitent une mise a jour de leur
 * constructeur pour rester compatibles.
 */
final class ConstructorSignatureAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par classe necessitant une mise a jour */
    private const MINUTES_PER_CLASS = 120;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /**
     * Noms courts des classes Sylius dont le constructeur a change.
     *
     * @var list<string>
     */
    private const CHANGED_CONSTRUCTORS = [
        'CheckoutStepsExtension',
        'PriceExtension',
        'VariantResolverExtension',
        'CurrencyExtension',
        'InventoryExtension',
        'LocaleExtension',
        'ConvertMoneyExtension',
        'FormatMoneyExtension',
        'AggregateAdjustmentsExtension',
        'AdminFilterSubscriber',
        'ResendOrderConfirmationEmailAction',
        'ResendShipmentConfirmationEmailAction',
        'ImpersonateUserController',
        'ShipmentShipListener',
        'OrderCompleteListener',
        'ContactController',
        'ZoneMatcher',
        'UnpaidOrdersStateUpdater',
        'ProductVariantPriceCalculator',
        'ImageUploader',
        'TaxRateResolver',
        'OrderPricesRecalculator',
        'SecurityController',
        'ChannelFactory',
    ];

    /** Expression reguliere pour detecter une clause extends */
    private const EXTENDS_REGEX = '/\bextends\s+([\\\\a-zA-Z0-9_]+)/';

    public function getName(): string
    {
        return 'Constructor Signature';
    }

    public function supports(MigrationReport $report): bool
    {
        $srcDir = $report->getProjectPath() . '/src';
        if (!is_dir($srcDir)) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        foreach ($finder as $file) {
            $content = $file->getContents();

            if ($this->containsExtendsPattern($content)) {
                return true;
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $srcDir = $report->getProjectPath() . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        $totalClasses = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = $file->getContents();
            $relativePath = $file->getRelativePathname();
            $lines = explode("\n", $content);

            /* Recherche de la clause extends sur chaque ligne */
            foreach ($lines as $index => $line) {
                if (preg_match(self::EXTENDS_REGEX, $line, $matches) !== 1) {
                    continue;
                }

                $extendedClass = $matches[1];
                $shortName = $this->extractShortName($extendedClass);

                if (!in_array($shortName, self::CHANGED_CONSTRUCTORS, true)) {
                    continue;
                }

                $totalClasses++;
                $lineNumber = $index + 1;

                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf(
                        'Classe etendant %s detectee dans %s ligne %d',
                        $shortName,
                        $relativePath,
                        $lineNumber,
                    ),
                    detail: sprintf(
                        'La classe dans %s etend %s dont le constructeur a change '
                        . 'dans Sylius 2.0. Si le constructeur est surcharge, '
                        . 'il doit etre adapte a la nouvelle signature.',
                        $relativePath,
                        $extendedClass,
                    ),
                    suggestion: sprintf(
                        'Verifier et adapter le constructeur de la classe qui etend %s. '
                        . 'Consulter le UPGRADE.md de Sylius pour la nouvelle signature.',
                        $shortName,
                    ),
                    file: $filePath,
                    line: $lineNumber,
                    codeSnippet: trim($line),
                    docUrl: self::DOC_URL,
                    estimatedMinutes: self::MINUTES_PER_CLASS,
                ));
            }
        }

        /* Resume global */
        if ($totalClasses > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d classe(s) etendant des classes Sylius avec constructeur modifie',
                    $totalClasses,
                ),
                detail: 'Le projet contient des classes qui etendent des classes Sylius '
                    . 'dont le constructeur a change dans Sylius 2.0. Chaque classe '
                    . 'doit etre verifiee et son constructeur adapte.',
                suggestion: 'Verifier chaque classe et adapter le constructeur selon '
                    . 'la nouvelle signature de Sylius 2.0.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $totalClasses * self::MINUTES_PER_CLASS,
            ));
        }
    }

    /**
     * Verifie si le contenu d'un fichier contient un extends vers une classe concernee.
     */
    private function containsExtendsPattern(string $content): bool
    {
        foreach (self::CHANGED_CONSTRUCTORS as $className) {
            if (str_contains($content, 'extends') && str_contains($content, $className)) {
                /* Verification plus precise avec regex */
                $pattern = '/\bextends\s+[\\\\a-zA-Z0-9_]*' . preg_quote($className, '/') . '\b/';
                if (preg_match($pattern, $content) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extrait le nom court d'un FQCN.
     */
    private function extractShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
