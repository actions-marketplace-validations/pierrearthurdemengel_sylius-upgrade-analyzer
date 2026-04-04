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
 * Analyseur de l'utilisation de sylius/calendar.
 * Sylius 2.0 remplace Sylius\Calendar\Provider\DateTimeProviderInterface
 * par Symfony\Component\Clock\ClockInterface.
 */
final class CalendarClockAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par usage detecte */
    private const MINUTES_PER_USAGE = 60;

    /** Namespace complet de l'interface depreciee */
    private const DEPRECATED_INTERFACE = 'Sylius\\Calendar\\Provider\\DateTimeProviderInterface';

    /** Nom du package deprecie dans composer.json */
    private const DEPRECATED_PACKAGE = 'sylius/calendar';

    public function getName(): string
    {
        return 'Calendar to Clock';
    }

    public function supports(MigrationReport $report): bool
    {
        $projectPath = $report->getProjectPath();

        /* Verification de la dependance dans composer.json */
        $composerJsonPath = $projectPath . '/composer.json';
        if (file_exists($composerJsonPath)) {
            $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
            if (is_array($composerData) && isset($composerData['require'][self::DEPRECATED_PACKAGE])) {
                return true;
            }
        }

        /* Verification de l'utilisation dans les fichiers PHP du src/ */
        $srcDir = $projectPath . '/src';
        if (is_dir($srcDir)) {
            $finder = new Finder();
            $finder->files()->in($srcDir)->name('*.php');

            foreach ($finder as $file) {
                $filePath = $file->getRealPath();
                if ($filePath === false) {
                    continue;
                }

                $content = (string) file_get_contents($filePath);
                if (str_contains($content, self::DEPRECATED_INTERFACE)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();

        /* Etape 1 : verification dans composer.json */
        $this->analyzeComposerJson($report, $projectPath);

        /* Etape 2 : recherche dans les fichiers PHP du src/ */
        $this->analyzePhpUsages($report, $projectPath);
    }

    /**
     * Verifie la presence de sylius/calendar dans composer.json.
     */
    private function analyzeComposerJson(MigrationReport $report, string $projectPath): void
    {
        $composerJsonPath = $projectPath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return;
        }

        if (isset($composerData['require'][self::DEPRECATED_PACKAGE])) {
            $version = $composerData['require'][self::DEPRECATED_PACKAGE];
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: 'Dependance sylius/calendar detectee dans composer.json',
                detail: sprintf(
                    'La version %s de sylius/calendar est installee. '
                    . 'Ce package est supprime dans Sylius 2.0 et remplace par symfony/clock.',
                    $version,
                ),
                suggestion: 'Remplacer sylius/calendar par symfony/clock dans composer.json '
                    . 'et utiliser Symfony\\Component\\Clock\\ClockInterface au lieu de DateTimeProviderInterface.',
                file: $composerJsonPath,
                estimatedMinutes: self::MINUTES_PER_USAGE,
            ));
        }
    }

    /**
     * Recherche les utilisations de DateTimeProviderInterface dans les fichiers PHP.
     * Detecte les instructions use et les references FQCN par recherche textuelle.
     */
    private function analyzePhpUsages(MigrationReport $report, string $projectPath): void
    {
        $srcDir = $projectPath . '/src';
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

            if (!str_contains($content, self::DEPRECATED_INTERFACE)) {
                continue;
            }

            /* Recherche des numeros de ligne contenant la reference */
            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                if (str_contains($line, self::DEPRECATED_INTERFACE)) {
                    $lineNumber = $index + 1;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Utilisation de DateTimeProviderInterface detectee dans %s',
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'La reference a %s en ligne %d de %s doit etre remplacee. '
                            . 'Cette interface est supprimee dans Sylius 2.0.',
                            self::DEPRECATED_INTERFACE,
                            $lineNumber,
                            $file->getRelativePathname(),
                        ),
                        suggestion: 'Remplacer Sylius\\Calendar\\Provider\\DateTimeProviderInterface '
                            . 'par Symfony\\Component\\Clock\\ClockInterface. '
                            . 'Remplacer les appels a $provider->now() par $clock->now().',
                        file: $filePath,
                        line: $lineNumber,
                        estimatedMinutes: self::MINUTES_PER_USAGE,
                    ));
                }
            }
        }
    }
}
