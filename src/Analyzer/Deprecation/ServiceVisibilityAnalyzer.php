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
 * Analyseur de la visibilite des services Sylius.
 * Dans Sylius 2.0, tous les services sont desormais prives par defaut.
 * Les acces directs au conteneur pour recuperer des services Sylius ne fonctionneront plus.
 * Cet analyseur detecte les appels $container->get('sylius.'), $this->get('sylius.'), etc.
 */
final class ServiceVisibilityAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par acces direct au conteneur */
    private const MINUTES_PER_ACCESS = 60;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /**
     * Patterns regex pour detecter les acces directs au conteneur pour les services Sylius.
     *
     * @var array<string, string>
     */
    private const ACCESS_PATTERNS = [
        'container_get' => '/\$container\s*->\s*get\s*\(\s*[\'"]sylius\./',
        'this_container_get' => '/\$this\s*->\s*container\s*->\s*get\s*\(\s*[\'"]sylius\./',
        'this_get' => '/\$this\s*->\s*get\s*\(\s*[\'"]sylius\./',
        'get_container_get' => '/->\s*getContainer\s*\(\s*\)\s*->\s*get\s*\(\s*[\'"]sylius\./',
    ];

    /**
     * Libelles pour chaque type d'acces direct.
     *
     * @var array<string, string>
     */
    private const PATTERN_LABELS = [
        'container_get' => '$container->get(\'sylius...\')',
        'this_container_get' => '$this->container->get(\'sylius...\')',
        'this_get' => '$this->get(\'sylius...\')',
        'get_container_get' => '->getContainer()->get(\'sylius...\')',
    ];

    public function getName(): string
    {
        return 'Service Visibility';
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
            $content = (string) file_get_contents((string) $file->getRealPath());
            foreach (self::ACCESS_PATTERNS as $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $srcDir = $projectPath . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        $accessCount = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;

                foreach (self::ACCESS_PATTERNS as $patternKey => $pattern) {
                    if (preg_match($pattern, $line) !== 1) {
                        continue;
                    }

                    $accessCount++;
                    $label = self::PATTERN_LABELS[$patternKey];

                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Acces direct au conteneur via %s dans %s',
                            $label,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le fichier %s ligne %d utilise %s pour acceder a un service Sylius. '
                            . 'Dans Sylius 2.0, tous les services sont prives par defaut. '
                            . 'L\'acces direct au conteneur ne fonctionnera plus.',
                            $file->getRelativePathname(),
                            $lineNumber,
                            $label,
                        ),
                        suggestion: 'Injecter le service Sylius via le constructeur (dependency injection) '
                            . 'au lieu d\'utiliser un acces direct au conteneur.',
                        file: $filePath,
                        line: $lineNumber,
                        codeSnippet: trim($line),
                        docUrl: self::DOC_URL,
                        estimatedMinutes: self::MINUTES_PER_ACCESS,
                    ));
                }
            }
        }

        /* Resume global */
        if ($accessCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d acces direct(s) au conteneur pour des services Sylius detecte(s)',
                    $accessCount,
                ),
                detail: 'Tous les services Sylius sont desormais prives dans Sylius 2.0. '
                    . 'Les acces directs au conteneur ($container->get(), $this->get(), etc.) '
                    . 'doivent etre remplaces par de l\'injection de dependances.',
                suggestion: 'Refactoriser chaque acces direct au conteneur en injection de dependances '
                    . 'via le constructeur ou l\'autowiring Symfony.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $accessCount * self::MINUTES_PER_ACCESS,
            ));
        }
    }
}
