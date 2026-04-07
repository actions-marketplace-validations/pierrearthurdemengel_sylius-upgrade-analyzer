<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Commente les proprietes et methodes depreciees du modele User dans Sylius 2.0.
 * Les champs locked, expiresAt, credentialsExpireAt et l'interface Serializable
 * sont supprimes. Ce fixer commente les declarations avec un marqueur TODO.
 */
final class UserModelFieldFixer implements AutoFixInterface
{
    private const TARGET_ANALYZER = 'User Model Field';

    /**
     * Methodes depreciees dont le corps peut etre simplifie a "return null;"
     * ou qui doivent etre supprimees.
     *
     * @var list<string>
     */
    private const REMOVABLE_METHODS = [
        'isLocked',
        'setLocked',
        'getExpiresAt',
        'setExpiresAt',
        'isExpired',
        'getCredentialsExpireAt',
        'setCredentialsExpireAt',
        'isCredentialsExpired',
    ];

    /**
     * Proprietes depreciees a commenter.
     *
     * @var list<string>
     */
    private const REMOVABLE_PROPERTIES = [
        'locked',
        'expiresAt',
        'credentialsExpireAt',
    ];

    public function getName(): string
    {
        return 'User Model Field Fixer';
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

        $targetIndex = $line - 1;
        if (!isset($lines[$targetIndex])) {
            return null;
        }

        $targetLine = $lines[$targetIndex];

        /* Detection du type de ligne : propriete, methode ou interface Serializable */
        if ($this->isSerializableDeclaration($targetLine)) {
            $fixedContent = $this->fixSerializableInterface($lines, $targetIndex);
        } elseif ($this->isPropertyDeclaration($targetLine)) {
            $fixedContent = $this->commentLine($lines, $targetIndex, 'propriete supprimee dans Sylius 2.0');
        } elseif ($this->isMethodDeclaration($targetLine)) {
            $fixedContent = $this->commentLine($lines, $targetIndex, 'methode supprimee dans Sylius 2.0');
        } else {
            return null;
        }

        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::MEDIUM,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Commentaire du champ/methode deprecie dans %s ligne %d.',
                basename($absolutePath),
                $line,
            ),
        );
    }

    /**
     * Commente une ligne avec un marqueur TODO.
     *
     * @param list<string> $lines
     */
    private function commentLine(array $lines, int $index, string $reason): string
    {
        $indent = $this->detectIndentation($lines[$index]);
        $lines[$index] = $indent . '// TODO: ' . $reason . ' — ' . trim($lines[$index]);

        return implode("\n", $lines);
    }

    /**
     * Retire Serializable de la clause implements.
     *
     * @param list<string> $lines
     */
    private function fixSerializableInterface(array $lines, int $index): string
    {
        $line = $lines[$index];

        /* Retrait de ", Serializable" ou "Serializable, " ou "Serializable" seul */
        $fixed = preg_replace('/,\s*\\\\?Serializable\b/', '', $line);
        if ($fixed === null) {
            $fixed = $line;
        }
        $fixed = preg_replace('/\\\\?Serializable\b\s*,?\s*/', '', $fixed);
        if ($fixed === null) {
            $fixed = $line;
        }

        /* Si implements est devenu vide, le supprimer */
        $fixed = preg_replace('/\bimplements\s*$/', '', $fixed);
        if ($fixed === null) {
            $fixed = $line;
        }

        $lines[$index] = $fixed;

        return implode("\n", $lines);
    }

    private function isSerializableDeclaration(string $line): bool
    {
        return preg_match('/\bimplements\b.*\bSerializable\b/', $line) === 1;
    }

    private function isPropertyDeclaration(string $line): bool
    {
        foreach (self::REMOVABLE_PROPERTIES as $prop) {
            if (preg_match('/\$' . preg_quote($prop, '/') . '\b/', $line) === 1
                && preg_match('/^\s*(private|protected|public)\s+/', $line) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function isMethodDeclaration(string $line): bool
    {
        foreach (self::REMOVABLE_METHODS as $method) {
            if (preg_match('/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/', $line) === 1) {
                return true;
            }
        }

        /* getSalt() est aussi une methode depreciee */
        return preg_match('/\bfunction\s+getSalt\s*\(/', $line) === 1;
    }

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
