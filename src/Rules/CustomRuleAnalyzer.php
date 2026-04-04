<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Rules;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Analyseur executant les regles personnalisees definies par l'utilisateur.
 * Charge les regles depuis .sylius-upgrade-rules.yaml et les applique
 * sur le code source du projet analyse.
 */
final class CustomRuleAnalyzer implements AnalyzerInterface
{
    /** Extensions de fichiers a analyser pour chaque type de regle */
    private const FILE_EXTENSIONS_BY_TYPE = [
        'php_class_usage' => ['php'],
        'php_method_call' => ['php'],
        'twig_function' => ['twig', 'html.twig'],
        'yaml_key' => ['yaml', 'yml'],
    ];

    /** Repertoires a exclure de l'analyse */
    private const EXCLUDED_DIRS = ['vendor', 'node_modules', 'var', 'cache', '.git'];

    public function __construct(
        private readonly CustomRuleLoader $ruleLoader,
    ) {
    }

    public function getName(): string
    {
        return 'Custom Rules';
    }

    public function supports(MigrationReport $report): bool
    {
        $configPath = $report->getProjectPath() . '/.sylius-upgrade-rules.yaml';

        return file_exists($configPath);
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();

        /* Chargement des regles personnalisees */
        try {
            $rules = $this->ruleLoader->load($projectPath);
        } catch (\InvalidArgumentException $e) {
            /* En cas d'erreur de chargement, on cree un issue d'avertissement */
            $report->addIssue(new MigrationIssue(
                severity: Severity::WARNING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: 'Erreur de chargement des regles personnalisees',
                detail: $e->getMessage(),
                suggestion: 'Verifier la syntaxe du fichier .sylius-upgrade-rules.yaml',
                file: '.sylius-upgrade-rules.yaml',
            ));

            return;
        }

        if (count($rules) === 0) {
            return;
        }

        /* Execution de chaque regle sur le code source */
        foreach ($rules as $rule) {
            $this->executeRule($rule, $projectPath, $report);
        }
    }

    /**
     * Execute une regle personnalisee sur les fichiers du projet.
     * Recherche le motif defini dans la regle dans tous les fichiers correspondants.
     */
    private function executeRule(CustomRule $rule, string $projectPath, MigrationReport $report): void
    {
        $extensions = self::FILE_EXTENSIONS_BY_TYPE[$rule->type] ?? ['php'];

        /* Construction du Finder pour parcourir les fichiers concernes */
        $finder = new Finder();
        $finder->files()->in($projectPath);

        foreach (self::EXCLUDED_DIRS as $excludedDir) {
            $finder->exclude($excludedDir);
        }

        foreach ($extensions as $extension) {
            $finder->name('*.' . $extension);
        }

        /* Construction du motif de recherche selon le type de regle */
        $searchPattern = $this->buildSearchPattern($rule);
        if ($searchPattern === null) {
            return;
        }

        foreach ($finder as $file) {
            $content = $file->getContents();
            $filePath = $file->getRelativePathname();

            $this->searchInContent($content, $filePath, $searchPattern, $rule, $report);
        }
    }

    /**
     * Construit un motif de recherche regex adapte au type de regle.
     * Retourne null si le motif ne peut pas etre construit.
     */
    private function buildSearchPattern(CustomRule $rule): ?string
    {
        $escapedPattern = preg_quote($rule->pattern, '/');

        return match ($rule->type) {
            /* Recherche d'utilisation de classe PHP (use, new, instanceof, type hints) */
            'php_class_usage' => '/(?:use\s+|new\s+|instanceof\s+|:\s*|,\s*)' . $escapedPattern . '(?:\s|;|\(|,|\))/m',

            /* Recherche d'appel de methode PHP */
            'php_method_call' => '/->(' . $escapedPattern . ')\s*\(/m',

            /* Recherche de fonction Twig */
            'twig_function' => '/(?:\{\{|\{%)\s*.*' . $escapedPattern . '\s*\(/m',

            /* Recherche de cle YAML */
            'yaml_key' => '/^\s*' . $escapedPattern . '\s*:/m',

            default => null,
        };
    }

    /**
     * Recherche le motif dans le contenu d'un fichier et cree des issues pour chaque correspondance.
     */
    private function searchInContent(
        string $content,
        string $filePath,
        string $searchPattern,
        CustomRule $rule,
        MigrationReport $report,
    ): void {
        /* Validation du regex avant utilisation */
        if (@preg_match($searchPattern, '') === false) {
            return;
        }

        $matches = [];
        if (preg_match_all($searchPattern, $content, $matches, \PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        foreach ($matches[0] as $match) {
            $matchText = $match[0];
            $matchOffset = $match[1];

            /* Calcul du numero de ligne a partir de l'offset */
            $lineNumber = substr_count($content, "\n", 0, $matchOffset) + 1;

            /* Extraction de l'extrait de code (la ligne correspondante) */
            $lines = explode("\n", $content);
            $codeSnippet = $lines[$lineNumber - 1] ?? trim($matchText);

            /* Resolution de la severite */
            $severity = Severity::tryFrom($rule->severity) ?? Severity::WARNING;

            /* Resolution de la categorie */
            $category = Category::tryFrom($rule->category) ?? Category::DEPRECATION;

            $report->addIssue(new MigrationIssue(
                severity: $severity,
                category: $category,
                analyzer: $this->getName(),
                message: sprintf('[%s] %s', $rule->name, $rule->message),
                detail: sprintf(
                    'Correspondance trouvee dans %s a la ligne %d : %s',
                    $filePath,
                    $lineNumber,
                    trim($codeSnippet),
                ),
                suggestion: $rule->suggestion,
                file: $filePath,
                line: $lineNumber,
                codeSnippet: trim($codeSnippet),
                estimatedMinutes: $rule->estimatedMinutes,
            ));
        }
    }
}
