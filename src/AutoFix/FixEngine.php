<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;

/**
 * Moteur d'orchestration des correctifs automatiques.
 * Parcourt les issues du rapport et applique les fixers compatibles.
 */
final class FixEngine
{
    /** @var list<AutoFixInterface> */
    private readonly array $fixers;

    /**
     * @param list<AutoFixInterface> $fixers Liste des fixers disponibles
     */
    public function __construct(array $fixers)
    {
        $this->fixers = $fixers;
    }

    /**
     * Genere les correctifs pour toutes les issues du rapport.
     * Chaque issue est testee contre tous les fixers disponibles.
     *
     * @return list<MigrationFix>
     */
    public function generateFixes(MigrationReport $report, string $projectPath): array
    {
        $fixes = [];

        foreach ($report->getIssues() as $issue) {
            foreach ($this->fixers as $fixer) {
                if (!$fixer->supports($issue)) {
                    continue;
                }

                $fix = $fixer->fix($issue, $projectPath);
                if ($fix !== null) {
                    $fixes[] = $fix;
                }
            }
        }

        return $fixes;
    }

    /**
     * Applique un correctif en ecrivant le contenu corrige dans le fichier cible.
     */
    public function applyFix(MigrationFix $fix): void
    {
        $directory = dirname($fix->filePath);

        /* Creation du repertoire parent si necessaire */
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fix->filePath, $fix->fixedContent);
    }

    /**
     * Genere un patch au format unified diff pour un ensemble de correctifs.
     *
     * @param list<MigrationFix> $fixes Liste des correctifs a inclure dans le patch
     * @return string Patch au format unified diff
     */
    public function generatePatch(array $fixes): string
    {
        $patch = '';

        foreach ($fixes as $fix) {
            $patch .= $this->generateUnifiedDiff($fix);
        }

        return $patch;
    }

    /**
     * Genere le diff unifie pour un seul correctif.
     */
    private function generateUnifiedDiff(MigrationFix $fix): string
    {
        $originalLines = explode("\n", $fix->originalContent);
        $fixedLines = explode("\n", $fix->fixedContent);

        $diff = sprintf("--- a/%s\n", $fix->filePath);
        $diff .= sprintf("+++ b/%s\n", $fix->filePath);

        /* Generation des hunks de differences */
        $hunks = $this->computeHunks($originalLines, $fixedLines);

        foreach ($hunks as $hunk) {
            $diff .= sprintf(
                "@@ -%d,%d +%d,%d @@\n",
                $hunk['originalStart'],
                $hunk['originalCount'],
                $hunk['fixedStart'],
                $hunk['fixedCount'],
            );

            foreach ($hunk['lines'] as $line) {
                $diff .= $line . "\n";
            }
        }

        return $diff;
    }

    /**
     * Calcule les hunks de differences entre deux ensembles de lignes.
     * Utilise un algorithme simplifie basee sur la comparaison ligne a ligne.
     *
     * @param list<string> $originalLines Lignes originales
     * @param list<string> $fixedLines    Lignes corrigees
     * @return list<array{originalStart: int, originalCount: int, fixedStart: int, fixedCount: int, lines: list<string>}>
     */
    private function computeHunks(array $originalLines, array $fixedLines): array
    {
        $hunks = [];
        $originalIndex = 0;
        $fixedIndex = 0;
        $originalTotal = count($originalLines);
        $fixedTotal = count($fixedLines);
        $contextLines = 3;

        /* Parcours parallele des deux ensembles de lignes */
        while ($originalIndex < $originalTotal || $fixedIndex < $fixedTotal) {
            /* Recherche du prochain bloc de differences */
            if (
                $originalIndex < $originalTotal
                && $fixedIndex < $fixedTotal
                && $originalLines[$originalIndex] === $fixedLines[$fixedIndex]
            ) {
                $originalIndex++;
                $fixedIndex++;
                continue;
            }

            /* Debut d'un hunk : collecte du contexte avant */
            $hunkOriginalStart = max(1, $originalIndex - $contextLines + 1);
            $hunkFixedStart = max(1, $fixedIndex - $contextLines + 1);
            $hunkLines = [];

            /* Ajout des lignes de contexte avant */
            for ($i = max(0, $originalIndex - $contextLines); $i < $originalIndex; $i++) {
                if ($i < $originalTotal) {
                    $hunkLines[] = ' ' . $originalLines[$i];
                }
            }

            /* Collecte des lignes modifiees */
            $diffOriginalEnd = $originalIndex;
            $diffFixedEnd = $fixedIndex;

            /* Avance jusqu'a retrouver une correspondance ou la fin */
            while ($diffOriginalEnd < $originalTotal || $diffFixedEnd < $fixedTotal) {
                /* Verification si les lignes correspondent a nouveau */
                if (
                    $diffOriginalEnd < $originalTotal
                    && $diffFixedEnd < $fixedTotal
                    && $originalLines[$diffOriginalEnd] === $fixedLines[$diffFixedEnd]
                ) {
                    /* Verification de la continuite : au moins $contextLines identiques */
                    $matchCount = 0;
                    while (
                        $diffOriginalEnd + $matchCount < $originalTotal
                        && $diffFixedEnd + $matchCount < $fixedTotal
                        && $originalLines[$diffOriginalEnd + $matchCount] === $fixedLines[$diffFixedEnd + $matchCount]
                    ) {
                        $matchCount++;
                        if ($matchCount >= $contextLines * 2) {
                            break;
                        }
                    }

                    if ($matchCount >= $contextLines * 2) {
                        break;
                    }
                }

                /* Ajout des lignes supprimees */
                if ($diffOriginalEnd < $originalTotal) {
                    $hunkLines[] = '-' . $originalLines[$diffOriginalEnd];
                    $diffOriginalEnd++;
                }

                /* Ajout des lignes ajoutees */
                if ($diffFixedEnd < $fixedTotal) {
                    $hunkLines[] = '+' . $fixedLines[$diffFixedEnd];
                    $diffFixedEnd++;
                }
            }

            /* Ajout des lignes de contexte apres */
            for ($i = 0; $i < $contextLines; $i++) {
                if ($diffOriginalEnd + $i < $originalTotal) {
                    $hunkLines[] = ' ' . $originalLines[$diffOriginalEnd + $i];
                }
            }

            $originalCount = ($diffOriginalEnd - $hunkOriginalStart + 1) + min($contextLines, $originalTotal - $diffOriginalEnd);
            $fixedCount = ($diffFixedEnd - $hunkFixedStart + 1) + min($contextLines, $fixedTotal - $diffFixedEnd);

            $hunks[] = [
                'originalStart' => $hunkOriginalStart,
                'originalCount' => max(0, $originalCount),
                'fixedStart' => $hunkFixedStart,
                'fixedCount' => max(0, $fixedCount),
                'lines' => $hunkLines,
            ];

            $originalIndex = $diffOriginalEnd;
            $fixedIndex = $diffFixedEnd;
        }

        return $hunks;
    }
}
