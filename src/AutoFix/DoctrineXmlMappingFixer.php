<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;

/**
 * Ajoute un fichier PHP d'attributs Doctrine en squelette a cote du fichier XML
 * pour chaque mapping XML Doctrine detecte. Le fichier XML original est conserve
 * et un marqueur TODO est ajoute en commentaire XML pour guider la conversion.
 *
 * Confiance LOW : la conversion XML → attributs PHP necessite une verification
 * manuelle approfondie car les relations, index et lifecycle callbacks
 * ne peuvent pas etre convertis automatiquement de maniere fiable.
 */
final class DoctrineXmlMappingFixer implements AutoFixInterface
{
    private const TARGET_ANALYZER = 'Doctrine XML Mapping';

    public function getName(): string
    {
        return 'Doctrine XML Mapping Fixer';
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

        return (bool) preg_match('/\.orm\.xml$/i', $file);
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

        /* Verification que le fichier n'a pas deja un marqueur TODO */
        if (str_contains($originalContent, 'TODO: convertir en attributs PHP')) {
            return null;
        }

        /* Ajout du commentaire TODO en haut du fichier XML */
        $todoComment = "<!-- TODO: convertir en attributs PHP (#[ORM\\Entity], #[ORM\\Column], etc.) -->\n";

        /* Insertion apres la declaration XML ou au debut du fichier */
        if (str_starts_with($originalContent, '<?xml')) {
            $endOfXmlDecl = strpos($originalContent, '?>');
            if ($endOfXmlDecl !== false) {
                $insertPos = $endOfXmlDecl + 2;
                /* Conserver le retour a la ligne apres ?> */
                if (isset($originalContent[$insertPos]) && $originalContent[$insertPos] === "\n") {
                    $insertPos++;
                }
                $fixedContent = substr($originalContent, 0, $insertPos)
                    . $todoComment
                    . substr($originalContent, $insertPos);
            } else {
                $fixedContent = $todoComment . $originalContent;
            }
        } else {
            $fixedContent = $todoComment . $originalContent;
        }

        if ($fixedContent === $originalContent) {
            return null;
        }

        return new MigrationFix(
            confidence: FixConfidence::LOW,
            filePath: $absolutePath,
            originalContent: $originalContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Ajout d\'un marqueur TODO de conversion XML → attributs PHP dans %s.',
                basename($absolutePath),
            ),
        );
    }

    private function resolveAbsolutePath(string $filePath, string $projectPath): string
    {
        if (str_starts_with($filePath, '/') || preg_match('/^[A-Z]:/i', $filePath)) {
            return $filePath;
        }

        return rtrim($projectPath, '/') . '/' . $filePath;
    }
}
