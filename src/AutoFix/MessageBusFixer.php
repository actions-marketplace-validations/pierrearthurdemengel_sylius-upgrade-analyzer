<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Fixer pour le renommage des bus de messages Sylius.
 * Remplace les anciennes references de bus par les nouvelles :
 *   - sylius_default.bus → sylius.command_bus
 *   - sylius_event.bus   → sylius.event_bus
 * Fonctionne sur les fichiers YAML et PHP.
 */
final class MessageBusFixer implements AutoFixInterface
{
    /** Nom de l'analyseur cible */
    private const TARGET_ANALYZER = 'Message Bus Rename';

    /**
     * Table de correspondance entre les anciens et nouveaux noms de bus.
     *
     * @var array<string, string>
     */
    private const BUS_MAPPING = [
        'sylius_default.bus' => 'sylius.command_bus',
        'sylius_event.bus' => 'sylius.event_bus',
    ];

    public function getName(): string
    {
        return 'Message Bus Fixer';
    }

    public function supports(MigrationIssue $issue): bool
    {
        return $issue->getAnalyzer() === self::TARGET_ANALYZER;
    }

    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix
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
        $fixedContent = $originalContent;

        /* Application de tous les remplacements de noms de bus */
        foreach (self::BUS_MAPPING as $oldName => $newName) {
            $fixedContent = $this->replaceInContent($fixedContent, $oldName, $newName, $absolutePath);
        }

        /* Si aucune modification n'a ete faite */
        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::HIGH,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Renommage des bus de messages dans %s : '
                . 'sylius_default.bus → sylius.command_bus, '
                . 'sylius_event.bus → sylius.event_bus.',
                basename($absolutePath),
            ),
        );
    }

    /**
     * Remplace les references d'un ancien nom de bus par le nouveau dans le contenu.
     * Adapte la strategie de remplacement au type de fichier (YAML ou PHP).
     */
    private function replaceInContent(string $content, string $oldName, string $newName, string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            return $this->replaceInPhp($content, $oldName, $newName);
        }

        if ($extension === 'yaml' || $extension === 'yml') {
            return $this->replaceInYaml($content, $oldName, $newName);
        }

        /* Remplacement generique pour les autres types de fichiers */
        return str_replace($oldName, $newName, $content);
    }

    /**
     * Remplace les references de bus dans un fichier PHP.
     * Gere les chaines entre guillemets simples et doubles.
     */
    private function replaceInPhp(string $content, string $oldName, string $newName): string
    {
        /* Remplacement dans les chaines entre guillemets simples */
        $content = str_replace("'" . $oldName . "'", "'" . $newName . "'", $content);

        /* Remplacement dans les chaines entre guillemets doubles */
        $content = str_replace('"' . $oldName . '"', '"' . $newName . '"', $content);

        /* Remplacement dans les attributs PHP 8 (ex: #[AsMessageHandler(bus: 'sylius_default.bus')]) */
        $content = (string) preg_replace(
            '/bus\s*:\s*[\'"]' . preg_quote($oldName, '/') . '[\'"]/',
            "bus: '" . $newName . "'",
            $content,
        );

        return $content;
    }

    /**
     * Remplace les references de bus dans un fichier YAML.
     * Gere les differentes syntaxes YAML (valeurs, cles de services, tags).
     */
    private function replaceInYaml(string $content, string $oldName, string $newName): string
    {
        /* Remplacement des valeurs entre guillemets */
        $content = str_replace("'" . $oldName . "'", "'" . $newName . "'", $content);
        $content = str_replace('"' . $oldName . '"', '"' . $newName . '"', $content);

        /* Remplacement des references de services (@sylius_default.bus) */
        $content = str_replace('@' . $oldName, '@' . $newName, $content);

        /* Remplacement des valeurs non quotees (ex: bus: sylius_default.bus) */
        $content = (string) preg_replace(
            '/^(\s*bus\s*:\s*)' . preg_quote($oldName, '/') . '(\s*)$/m',
            '${1}' . $newName . '${2}',
            $content,
        );

        /* Remplacement dans les arguments de services */
        $content = (string) preg_replace(
            '/^(\s*-\s*)' . preg_quote($oldName, '/') . '(\s*)$/m',
            '${1}' . $newName . '${2}',
            $content,
        );

        return $content;
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
