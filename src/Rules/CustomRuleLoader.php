<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Rules;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Charge les regles personnalisees depuis le fichier .sylius-upgrade-rules.yaml
 * situe a la racine du projet analyse.
 * Valide le schema YAML et retourne un tableau d'objets CustomRule.
 */
final class CustomRuleLoader
{
    /** Nom du fichier de configuration des regles personnalisees */
    private const CONFIG_FILENAME = '.sylius-upgrade-rules.yaml';

    /** Types de regles autorises */
    private const VALID_TYPES = [
        'php_class_usage',
        'php_method_call',
        'twig_function',
        'yaml_key',
    ];

    /** Severites autorisees */
    private const VALID_SEVERITIES = [
        'breaking',
        'warning',
        'suggestion',
    ];

    /** Categories autorisees */
    private const VALID_CATEGORIES = [
        'twig',
        'deprecation',
        'plugin',
        'grid',
        'resource',
        'frontend',
        'api',
    ];

    /**
     * Charge les regles personnalisees depuis le fichier de configuration du projet.
     *
     * @param string $projectPath Chemin racine du projet analyse
     *
     * @return list<CustomRule> Liste des regles valides chargees
     *
     * @throws \InvalidArgumentException Si le fichier YAML est invalide ou si une regle ne respecte pas le schema
     */
    public function load(string $projectPath): array
    {
        $configPath = rtrim($projectPath, '/\\') . '/' . self::CONFIG_FILENAME;

        if (!file_exists($configPath)) {
            return [];
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return [];
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException(
                sprintf('Erreur de syntaxe YAML dans %s : %s', self::CONFIG_FILENAME, $e->getMessage()),
                0,
                $e,
            );
        }

        if (!is_array($parsed)) {
            return [];
        }

        $rulesData = $parsed['rules'] ?? [];
        if (!is_array($rulesData)) {
            throw new \InvalidArgumentException(
                sprintf('La cle "rules" dans %s doit etre un tableau.', self::CONFIG_FILENAME),
            );
        }

        $rules = [];
        foreach ($rulesData as $index => $ruleData) {
            $rules[] = $this->validateAndCreateRule($ruleData, $index);
        }

        return $rules;
    }

    /**
     * Valide les donnees d'une regle et cree un objet CustomRule.
     *
     * @param mixed $ruleData Donnees brutes de la regle depuis le YAML
     * @param int|string $index   Index de la regle dans le tableau pour les messages d'erreur
     *
     * @throws \InvalidArgumentException Si une propriete requise est manquante ou invalide
     */
    private function validateAndCreateRule(mixed $ruleData, int|string $index): CustomRule
    {
        if (!is_array($ruleData)) {
            throw new \InvalidArgumentException(
                sprintf('La regle #%s doit etre un tableau associatif.', $index),
            );
        }

        /* Verification des champs requis */
        $requiredFields = ['name', 'pattern', 'type', 'severity', 'category', 'message', 'suggestion'];
        foreach ($requiredFields as $field) {
            if (!isset($ruleData[$field]) || !is_string($ruleData[$field]) || trim($ruleData[$field]) === '') {
                throw new \InvalidArgumentException(
                    sprintf('La regle #%s doit definir un champ "%s" non vide (chaine de caracteres).', $index, $field),
                );
            }
        }

        /* Validation du type */
        $type = $ruleData['type'];
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La regle #%s a un type invalide "%s". Types autorises : %s',
                    $index,
                    $type,
                    implode(', ', self::VALID_TYPES),
                ),
            );
        }

        /* Validation de la severite */
        $severity = $ruleData['severity'];
        if (!in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La regle #%s a une severite invalide "%s". Severites autorisees : %s',
                    $index,
                    $severity,
                    implode(', ', self::VALID_SEVERITIES),
                ),
            );
        }

        /* Validation de la categorie */
        $category = $ruleData['category'];
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La regle #%s a une categorie invalide "%s". Categories autorisees : %s',
                    $index,
                    $category,
                    implode(', ', self::VALID_CATEGORIES),
                ),
            );
        }

        /* Validation du champ estimatedMinutes (optionnel, defaut a 0) */
        $estimatedMinutes = 0;
        if (isset($ruleData['estimated_minutes'])) {
            if (!is_int($ruleData['estimated_minutes']) || $ruleData['estimated_minutes'] < 0) {
                throw new \InvalidArgumentException(
                    sprintf('La regle #%s a un champ "estimated_minutes" invalide. Doit etre un entier positif.', $index),
                );
            }
            $estimatedMinutes = $ruleData['estimated_minutes'];
        }

        return new CustomRule(
            name: $ruleData['name'],
            pattern: $ruleData['pattern'],
            type: $type,
            severity: $severity,
            category: $category,
            message: $ruleData['message'],
            suggestion: $ruleData['suggestion'],
            estimatedMinutes: $estimatedMinutes,
        );
    }
}
