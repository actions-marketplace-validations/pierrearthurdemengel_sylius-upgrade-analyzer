<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Ajoute un marqueur TODO sur les constructeurs surcharges de classes Sylius
 * dont la signature a change dans Sylius 2.0. Le correctif commente la ligne
 * du constructeur pour forcer une verification manuelle.
 */
final class ConstructorSignatureFixer implements AutoFixInterface
{
    private const TARGET_ANALYZER = 'Constructor Signature';

    /**
     * Noms courts des classes Sylius dont le constructeur a change.
     * Doit rester synchronise avec ConstructorSignatureAnalyzer::CHANGED_CONSTRUCTORS.
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

    public function getName(): string
    {
        return 'Constructor Signature Fixer';
    }

    public function supports(MigrationIssue $issue): bool
    {
        if ($issue->getAnalyzer() !== self::TARGET_ANALYZER) {
            return false;
        }

        $file = $issue->getFile();
        if ($file === null) {
            return false;
        }

        return (bool) preg_match('/\.php$/i', $file);
    }

    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        $filePath = $issue->getFile();
        $line = $issue->getLine();
        if ($filePath === null || $line === null) {
            return null;
        }

        $absolutePath = $this->resolveAbsolutePath($filePath, $projectPath);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $originalContent = (string) file_get_contents($absolutePath);
        $lines = explode("\n", $originalContent);

        /* Recherche de la ligne extends pour identifier la classe parente */
        $targetIndex = $line - 1;
        if (!isset($lines[$targetIndex])) {
            return null;
        }

        $extendsLine = $lines[$targetIndex];
        $parentClass = $this->extractParentClassName($extendsLine);
        if ($parentClass === null) {
            return null;
        }

        /* Recherche du constructeur dans le fichier */
        $constructorIndex = $this->findConstructorLine($lines);
        if ($constructorIndex === null) {
            return null;
        }

        /* Verification que le constructeur n'a pas deja un marqueur TODO */
        if ($constructorIndex > 0 && str_contains($lines[$constructorIndex - 1], 'TODO')) {
            return null;
        }

        /* Ajout du marqueur TODO au-dessus du constructeur */
        $indent = $this->detectIndentation($lines[$constructorIndex]);
        $todoComment = $indent . '// TODO: signature du constructeur parent ' . $parentClass
            . ' modifiee dans Sylius 2.0 — adapter les arguments';
        array_splice($lines, $constructorIndex, 0, [$todoComment]);

        $fixedContent = implode("\n", $lines);
        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::MEDIUM,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Ajout d\'un marqueur TODO sur le constructeur dans %s (parent : %s).',
                basename($absolutePath),
                $parentClass,
            ),
        );
    }

    /**
     * Extrait le nom court de la classe parente depuis la ligne extends.
     */
    private function extractParentClassName(string $line): ?string
    {
        if (preg_match('/\bextends\s+([\\\\a-zA-Z0-9_]+)/', $line, $matches) !== 1) {
            return null;
        }

        $fqcn = $matches[1];
        $parts = explode('\\', $fqcn);
        $shortName = end($parts);

        if (!in_array($shortName, self::CHANGED_CONSTRUCTORS, true)) {
            return null;
        }

        return $shortName;
    }

    /**
     * Recherche la ligne du constructeur __construct dans le fichier.
     *
     * @param list<string> $lines
     */
    private function findConstructorLine(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/\bfunction\s+__construct\s*\(/', $line) === 1) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Detecte l'indentation de la ligne donnee.
     */
    private function detectIndentation(string $line): string
    {
        if (preg_match('/^(\s*)/', $line, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function resolveAbsolutePath(string $filePath, string $projectPath): string
    {
        if (str_starts_with($filePath, '/') || preg_match('/^[A-Z]:/i', $filePath)) {
            return $filePath;
        }

        return rtrim($projectPath, '/') . '/' . $filePath;
    }
}
