<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use Symfony\Component\Yaml\Yaml;

/**
 * Fixer pour la migration des encodeurs de mots de passe vers password_hashers.
 * Genere la configuration password_hashers equivalente et des suggestions
 * pour supprimer la methode getSalt() des entites.
 */
final class SecurityConfigFixer implements AutoFixInterface
{
    /** Nom de l'analyseur cible pour la configuration encoders */
    private const TARGET_ANALYZER = 'User Encoder';

    /** Motif de detection des issues de configuration encoders */
    private const ENCODER_CONFIG_PATTERN = '/Configuration "encoders:" depreciee/';

    /** Motif de detection des issues getSalt() */
    private const GET_SALT_PATTERN = '/Methode getSalt\(\) detectee/';

    public function getName(): string
    {
        return 'Security Config Fixer';
    }

    public function supports(MigrationIssue $issue): bool
    {
        if ($issue->getAnalyzer() !== self::TARGET_ANALYZER) {
            return false;
        }

        /* Support des issues de configuration encoders et getSalt() */
        return (bool) preg_match(self::ENCODER_CONFIG_PATTERN, $issue->getMessage())
            || (bool) preg_match(self::GET_SALT_PATTERN, $issue->getMessage());
    }

    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        /* Dispatch selon le type d'issue */
        if (preg_match(self::ENCODER_CONFIG_PATTERN, $issue->getMessage())) {
            return $this->fixEncoderConfig($issue, $projectPath);
        }

        if (preg_match(self::GET_SALT_PATTERN, $issue->getMessage())) {
            return $this->fixGetSaltMethod($issue, $projectPath);
        }

        return null;
    }

    /**
     * Corrige la configuration security.yaml en remplacant encoders par password_hashers.
     */
    private function fixEncoderConfig(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        $filePath = $issue->getFile();
        if ($filePath === null) {
            return null;
        }

        $absolutePath = $this->resolveAbsolutePath($filePath, $projectPath);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $originalContent = (string) file_get_contents($absolutePath);

        try {
            $config = Yaml::parseFile($absolutePath);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($config)) {
            return null;
        }

        /* Extraction de la configuration de securite */
        $securityConfig = $config['security'] ?? $config;
        if (!is_array($securityConfig) || !isset($securityConfig['encoders'])) {
            return null;
        }

        $encoders = $securityConfig['encoders'];
        if (!is_array($encoders)) {
            return null;
        }

        /* Conversion des encoders en password_hashers */
        $passwordHashers = $this->convertEncodersToHashers($encoders);

        /* Remplacement dans la configuration */
        if (isset($config['security'])) {
            unset($config['security']['encoders']);
            $config['security']['password_hashers'] = $passwordHashers;
        } else {
            unset($config['encoders']);
            $config['password_hashers'] = $passwordHashers;
        }

        $fixedContent = Yaml::dump($config, 6, 4);

        return new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Remplacement de "security.encoders" par "security.password_hashers" dans %s. '
                . 'Les algorithmes ont ete mis a jour vers les equivalents modernes.',
                basename($absolutePath),
            ),
        );
    }

    /**
     * Genere une suggestion de correction pour la methode getSalt().
     */
    private function fixGetSaltMethod(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        $filePath = $issue->getFile();
        if ($filePath === null) {
            return null;
        }

        $absolutePath = $this->resolveAbsolutePath($filePath, $projectPath);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $originalContent = (string) file_get_contents($absolutePath);

        /* Remplacement de la methode getSalt() par une version retournant null */
        $fixedContent = (string) preg_replace(
            '/public\s+function\s+getSalt\s*\(\s*\)\s*:\s*\??\s*string\s*\{[^}]*\}/s',
            "public function getSalt(): ?string\n    {\n        /* Les algorithmes modernes (bcrypt, argon2) gerent le sel automatiquement */\n        return null;\n    }",
            $originalContent,
        );

        /* Si aucune modification n'a ete faite, essai avec un motif plus souple */
        if ($fixedContent === $originalContent) {
            $fixedContent = (string) preg_replace(
                '/public\s+function\s+getSalt\s*\(\s*\)[^{]*\{[^}]*\}/s',
                "public function getSalt(): ?string\n    {\n        /* Les algorithmes modernes (bcrypt, argon2) gerent le sel automatiquement */\n        return null;\n    }",
                $originalContent,
            );
        }

        /* Si toujours pas de modification, la correction est impossible */
        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Simplification de la methode getSalt() dans %s pour retourner null. '
                . 'Les algorithmes modernes gerent le sel automatiquement.',
                basename($absolutePath),
            ),
        );
    }

    /**
     * Convertit les definitions d'encoders en password_hashers.
     *
     * @param array<string, mixed> $encoders Configuration des encoders
     * @return array<string, mixed> Configuration des password_hashers
     */
    private function convertEncodersToHashers(array $encoders): array
    {
        $hashers = [];

        foreach ($encoders as $className => $encoderConfig) {
            if (is_string($encoderConfig)) {
                /* Configuration simple : nom de l'algorithme */
                $hashers[$className] = $this->convertAlgorithm($encoderConfig);
                continue;
            }

            if (!is_array($encoderConfig)) {
                $hashers[$className] = 'auto';
                continue;
            }

            /* Configuration detaillee */
            $algorithm = $encoderConfig['algorithm'] ?? $encoderConfig['id'] ?? null;

            if ($algorithm === null) {
                $hashers[$className] = 'auto';
                continue;
            }

            $hasherConfig = $this->convertAlgorithmConfig($algorithm, $encoderConfig);
            $hashers[$className] = $hasherConfig;
        }

        return $hashers;
    }

    /**
     * Convertit le nom d'un algorithme d'encodeur vers son equivalent hasher.
     */
    private function convertAlgorithm(string $algorithm): string
    {
        return match (strtolower($algorithm)) {
            'bcrypt' => 'auto',
            'argon2i' => 'auto',
            'argon2id' => 'auto',
            'sha512' => 'auto',
            'md5' => 'auto',
            'plaintext' => 'plaintext',
            'native' => 'auto',
            'sodium' => 'sodium',
            default => 'auto',
        };
    }

    /**
     * Convertit une configuration d'algorithme detaillee.
     *
     * @param string               $algorithm     Nom de l'algorithme
     * @param array<string, mixed> $encoderConfig Configuration complete de l'encodeur
     * @return string|array<string, mixed> Configuration du hasher
     */
    private function convertAlgorithmConfig(string $algorithm, array $encoderConfig): string|array
    {
        $convertedAlgorithm = $this->convertAlgorithm($algorithm);

        /* Si l'algorithme est simplement "auto", pas besoin de configuration detaillee */
        if ($convertedAlgorithm === 'auto' && !isset($encoderConfig['cost']) && !isset($encoderConfig['memory_cost'])) {
            return 'auto';
        }

        $hasherConfig = ['algorithm' => $convertedAlgorithm];

        /* Migration du cout pour bcrypt */
        if (isset($encoderConfig['cost'])) {
            $hasherConfig['cost'] = $encoderConfig['cost'];
        }

        /* Migration des parametres argon2 */
        if (isset($encoderConfig['memory_cost'])) {
            $hasherConfig['memory_cost'] = $encoderConfig['memory_cost'];
        }
        if (isset($encoderConfig['time_cost'])) {
            $hasherConfig['time_cost'] = $encoderConfig['time_cost'];
        }

        return $hasherConfig;
    }

    /**
     * Resout le chemin absolu d'un fichier.
     */
    private function resolveAbsolutePath(string $filePath, string $projectPath): string
    {
        if (str_starts_with($filePath, '/') || preg_match('/^[A-Z]:/i', $filePath)) {
            return $filePath;
        }

        return rtrim($projectPath, '/') . '/' . $filePath;
    }
}
