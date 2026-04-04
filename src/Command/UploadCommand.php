<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Command;

use PierreArthur\SyliusUpgradeAnalyzer\Exception\LicenseExpiredException;
use PierreArthur\SyliusUpgradeAnalyzer\Exception\ServiceUnavailableException;
use PierreArthur\SyliusUpgradeAnalyzer\Report\ReportUploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande d'envoi d'un rapport JSON vers le service distant
 * pour génération et téléchargement d'un rapport PDF.
 */
#[AsCommand(
    name: 'sylius-upgrade:upload',
    description: 'Envoie un rapport JSON au service distant et télécharge le PDF généré',
)]
final class UploadCommand extends Command
{
    public function __construct(
        private readonly ReportUploader $uploader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'json-file',
                InputArgument::REQUIRED,
                'Chemin vers le fichier JSON du rapport de migration',
            )
            ->addOption(
                'api-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Clé API pour l\'authentification (ou variable d\'environnement SYLIUS_UPGRADE_API_KEY)',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Chemin du fichier PDF de sortie',
                'migration-report.pdf',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /* Résolution de la clé API depuis l'option ou la variable d'environnement */
        $apiKey = $input->getOption('api-key');
        if (!is_string($apiKey) || $apiKey === '') {
            $envKey = $_ENV['SYLIUS_UPGRADE_API_KEY'] ?? getenv('SYLIUS_UPGRADE_API_KEY');
            $apiKey = is_string($envKey) ? $envKey : '';
        }

        if ($apiKey === '') {
            $io->error('Clé API requise. Utilisez --api-key ou définissez la variable d\'environnement SYLIUS_UPGRADE_API_KEY.');

            return Command::FAILURE;
        }

        /* Lecture du fichier JSON */
        $jsonFile = $input->getArgument('json-file');
        if (!file_exists($jsonFile) || !is_readable($jsonFile)) {
            $io->error(sprintf('Le fichier JSON est introuvable ou illisible : %s', $jsonFile));

            return Command::FAILURE;
        }

        $jsonContent = file_get_contents($jsonFile);
        if ($jsonContent === false) {
            $io->error(sprintf('Impossible de lire le fichier : %s', $jsonFile));

            return Command::FAILURE;
        }

        $reportData = json_decode($jsonContent, true);
        if (!is_array($reportData)) {
            $io->error('Le fichier JSON est invalide ou ne contient pas un rapport valide.');

            return Command::FAILURE;
        }

        /* Reconstruction du rapport depuis les données JSON */
        $report = $this->rebuildReport($reportData);

        $outputPath = $input->getOption('output');

        /* Barre de progression pour le suivi de l'opération */
        $progressBar = new ProgressBar($output, 3);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');

        try {
            /* Étape 1 : Envoi du rapport */
            $progressBar->setMessage('Envoi du rapport au service...');
            $progressBar->start();

            $pdfUrl = $this->uploader->upload($report, $apiKey);
            $progressBar->advance();

            /* Étape 2 : Téléchargement du PDF */
            $progressBar->setMessage('Téléchargement du PDF...');
            $this->uploader->downloadPdf($pdfUrl, $outputPath);
            $progressBar->advance();

            /* Étape 3 : Finalisation */
            $progressBar->setMessage('Terminé');
            $progressBar->advance();
            $progressBar->finish();
            $io->newLine(2);

            $io->success(sprintf('Rapport PDF téléchargé avec succès : %s', $outputPath));

            return Command::SUCCESS;
        } catch (LicenseExpiredException $exception) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error(sprintf('Erreur d\'authentification : %s', $exception->getMessage()));

            return Command::FAILURE;
        } catch (ServiceUnavailableException $exception) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error(sprintf('Service indisponible : %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Reconstruit un objet MigrationReport à partir des données JSON décodées.
     *
     * @param array<string, mixed> $data Données du rapport
     */
    private function rebuildReport(array $data): \PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport
    {
        $meta = $data['meta'] ?? [];
        $analyzedAt = isset($meta['analyzed_at'])
            ? new \DateTimeImmutable($meta['analyzed_at'])
            : new \DateTimeImmutable();

        $report = new \PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport(
            startedAt: $analyzedAt,
            detectedSyliusVersion: $meta['version'] ?? null,
            targetVersion: $meta['target_version'] ?? '2.0',
            projectPath: '.',
        );

        /* Reconstruction des problèmes depuis les données JSON groupées par catégorie */
        $issues = $data['issues'] ?? [];
        foreach ($issues as $categoryIssues) {
            if (!is_array($categoryIssues)) {
                continue;
            }

            foreach ($categoryIssues as $issueData) {
                if (!is_array($issueData)) {
                    continue;
                }

                $severity = \PierreArthur\SyliusUpgradeAnalyzer\Model\Severity::tryFrom($issueData['severity'] ?? '');
                $category = \PierreArthur\SyliusUpgradeAnalyzer\Model\Category::tryFrom($issueData['category'] ?? '');

                if ($severity === null || $category === null) {
                    continue;
                }

                $report->addIssue(new \PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue(
                    severity: $severity,
                    category: $category,
                    analyzer: $issueData['analyzer'] ?? '',
                    message: $issueData['message'] ?? '',
                    detail: $issueData['detail'] ?? '',
                    suggestion: $issueData['suggestion'] ?? '',
                    file: $issueData['file'] ?? null,
                    line: isset($issueData['line']) ? (int) $issueData['line'] : null,
                    codeSnippet: $issueData['code_snippet'] ?? null,
                    docUrl: $issueData['doc_url'] ?? null,
                    estimatedMinutes: (int) ($issueData['estimated_minutes'] ?? 0),
                ));
            }
        }

        $report->complete();

        return $report;
    }
}
