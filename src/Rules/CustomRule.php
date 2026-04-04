<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Rules;

/**
 * Represente une regle personnalisee definie par l'utilisateur.
 * Les regles sont chargees depuis le fichier .sylius-upgrade-rules.yaml
 * a la racine du projet analyse.
 */
final readonly class CustomRule
{
    /**
     * @param string $name             Nom unique de la regle
     * @param string $pattern          Motif de recherche (regex ou chaine)
     * @param string $type             Type de regle (php_class_usage, php_method_call, twig_function, yaml_key)
     * @param string $severity         Severite de la regle (breaking, warning, suggestion)
     * @param string $category         Categorie de la regle (twig, deprecation, plugin, grid, resource, frontend, api)
     * @param string $message          Message principal decrivant le probleme
     * @param string $suggestion       Suggestion de correction
     * @param int    $estimatedMinutes Estimation du temps de correction en minutes
     */
    public function __construct(
        public string $name,
        public string $pattern,
        public string $type,
        public string $severity,
        public string $category,
        public string $message,
        public string $suggestion,
        public int $estimatedMinutes,
    ) {
    }
}
