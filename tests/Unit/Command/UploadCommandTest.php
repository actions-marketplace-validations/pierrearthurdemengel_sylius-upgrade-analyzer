<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Command\UploadCommand;
use PierreArthur\SyliusUpgradeAnalyzer\Report\ReportUploader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour la commande d'upload de rapports.
 * Utilise CommandTester avec un ReportUploader reel construit sur un mock HttpClient.
 * ReportUploader est final, on ne peut pas le mocker directement.
 */
final class UploadCommandTest extends TestCase
{
    /** Fichier JSON temporaire pour les tests */
    private ?string $tempJsonFile = null;

    /**
     * Nettoie les fichiers temporaires apres chaque test.
     */
    protected function tearDown(): void
    {
        if ($this->tempJsonFile !== null && file_exists($this->tempJsonFile)) {
            unlink($this->tempJsonFile);
        }
    }

    /**
     * Cree un fichier JSON temporaire contenant un rapport de migration valide.
     */
    private function createTempJsonFile(): string
    {
        $reportData = [
            'meta' => [
                'version' => '1.12.0',
                'target_version' => '2.0',
                'analysis_duration_seconds' => 5,
                'analyzed_at' => '2025-01-15T10:00:00+00:00',
            ],
            'summary' => [
                'complexity' => 'moderate',
                'total_hours' => 10.0,
                'issues_count' => 2,
                'breaking_count' => 0,
                'warning_count' => 2,
                'suggestion_count' => 0,
            ],
            'estimated_hours_by_category' => [
                'frontend' => 5.0,
                'grid' => 5.0,
            ],
            'issues' => [
                'frontend' => [
                    [
                        'severity' => 'warning',
                        'category' => 'frontend',
                        'analyzer' => 'Semantic UI',
                        'message' => 'Classes Semantic UI detectees',
                        'detail' => 'Le fichier layout.html.twig contient des classes CSS Semantic UI.',
                        'suggestion' => 'Migrer vers le nouveau systeme front-end.',
                        'file' => null,
                        'line' => null,
                        'code_snippet' => null,
                        'doc_url' => null,
                        'estimated_minutes' => 60,
                    ],
                ],
                'grid' => [
                    [
                        'severity' => 'warning',
                        'category' => 'grid',
                        'analyzer' => 'Grid Customization',
                        'message' => '1 definition(s) de grille(s) YAML detectee(s)',
                        'detail' => 'Les grilles suivantes sont definies en YAML.',
                        'suggestion' => 'Migrer les definitions de grilles.',
                        'file' => null,
                        'line' => null,
                        'code_snippet' => null,
                        'doc_url' => null,
                        'estimated_minutes' => 60,
                    ],
                ],
            ],
        ];

        $this->tempJsonFile = sys_get_temp_dir() . '/sylius-test-report-' . uniqid() . '.json';
        file_put_contents($this->tempJsonFile, json_encode($reportData, \JSON_PRETTY_PRINT));

        return $this->tempJsonFile;
    }

    /**
     * Cree un mock de ResponseInterface avec le code HTTP et les donnees specifies.
     *
     * @param array<string, mixed>|null $responseData Donnees JSON de la reponse
     */
    private function createMockResponse(int $statusCode, ?array $responseData = null, ?string $content = null): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        if ($responseData !== null) {
            $response->method('toArray')->willReturn($responseData);
        }

        if ($content !== null) {
            $response->method('getContent')->willReturn($content);
        }

        return $response;
    }

    /**
     * Verifie que la commande reussit avec un fichier JSON valide et une cle API.
     * Le HttpClient simule un upload reussi puis un telechargement PDF reussi.
     */
    #[Test]
    public function testExecuteWithValidJsonFile(): void
    {
        $jsonFile = $this->createTempJsonFile();
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/abc123.pdf';
        $outputPath = sys_get_temp_dir() . '/sylius-cmd-test-' . uniqid() . '.pdf';

        /* Mock de la reponse d'upload (POST) */
        $uploadResponse = $this->createMockResponse(200, ['pdf_url' => $pdfUrl]);

        /* Mock de la reponse de telechargement (GET) */
        $downloadResponse = $this->createMockResponse(200, null, '%PDF-1.4 fake');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method) use ($uploadResponse, $downloadResponse): ResponseInterface {
                /* POST pour l'upload, GET pour le telechargement */
                return $method === 'POST' ? $uploadResponse : $downloadResponse;
            });

        $uploader = new ReportUploader($httpClient);
        $command = new UploadCommand($uploader);
        $tester = new CommandTester($command);

        try {
            $tester->execute([
                'json-file' => $jsonFile,
                '--api-key' => 'test-api-key',
                '--output' => $outputPath,
            ]);

            /* La commande doit se terminer avec le code de succes */
            self::assertSame(Command::SUCCESS, $tester->getStatusCode());
            self::assertStringContainsString($outputPath, $tester->getDisplay());
        } finally {
            /* Nettoyage du fichier PDF genere */
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * Verifie que la commande echoue quand le fichier JSON est introuvable.
     * Le message d'erreur doit indiquer que le fichier est introuvable.
     */
    #[Test]
    public function testExecuteWithMissingFile(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $uploader = new ReportUploader($httpClient);
        $command = new UploadCommand($uploader);
        $tester = new CommandTester($command);

        $tester->execute([
            'json-file' => '/chemin/inexistant/rapport.json',
            '--api-key' => 'test-api-key',
        ]);

        /* La commande doit echouer avec le code FAILURE */
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('introuvable', $tester->getDisplay());
    }

    /**
     * Verifie que la commande echoue quand la cle API est manquante.
     * Le message d'erreur doit indiquer que la cle API est requise.
     */
    #[Test]
    public function testExecuteWithMissingApiKey(): void
    {
        $jsonFile = $this->createTempJsonFile();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $uploader = new ReportUploader($httpClient);
        $command = new UploadCommand($uploader);
        $tester = new CommandTester($command);

        /* Nettoyage des variables d'environnement pour eviter les interferences */
        $previousEnv = $_ENV['SYLIUS_UPGRADE_API_KEY'] ?? null;
        unset($_ENV['SYLIUS_UPGRADE_API_KEY']);
        putenv('SYLIUS_UPGRADE_API_KEY');

        try {
            $tester->execute([
                'json-file' => $jsonFile,
            ]);

            /* La commande doit echouer avec le code FAILURE */
            self::assertSame(Command::FAILURE, $tester->getStatusCode());
            self::assertStringContainsString('API', $tester->getDisplay());
        } finally {
            /* Restauration de la variable d'environnement */
            if ($previousEnv !== null) {
                $_ENV['SYLIUS_UPGRADE_API_KEY'] = $previousEnv;
                putenv('SYLIUS_UPGRADE_API_KEY=' . $previousEnv);
            }
        }
    }

    /**
     * Verifie que la commande telecharge le PDF apres un upload reussi.
     * Le fichier PDF doit etre telecharge a l'emplacement specifie par --output.
     */
    #[Test]
    public function testExecuteDownloadsPdf(): void
    {
        $jsonFile = $this->createTempJsonFile();
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/def456.pdf';
        $outputPath = sys_get_temp_dir() . '/sylius-cmd-pdf-' . uniqid() . '.pdf';
        $pdfContent = '%PDF-1.4 contenu du rapport PDF';

        /* Mock de la reponse d'upload (POST) */
        $uploadResponse = $this->createMockResponse(200, ['pdf_url' => $pdfUrl]);

        /* Mock de la reponse de telechargement (GET) */
        $downloadResponse = $this->createMockResponse(200, null, $pdfContent);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method) use ($uploadResponse, $downloadResponse): ResponseInterface {
                return $method === 'POST' ? $uploadResponse : $downloadResponse;
            });

        $uploader = new ReportUploader($httpClient);
        $command = new UploadCommand($uploader);
        $tester = new CommandTester($command);

        try {
            $tester->execute([
                'json-file' => $jsonFile,
                '--api-key' => 'test-api-key',
                '--output' => $outputPath,
            ]);

            /* La commande doit reussir */
            self::assertSame(Command::SUCCESS, $tester->getStatusCode());

            /* Le fichier PDF doit exister avec le bon contenu */
            self::assertFileExists($outputPath);
            self::assertSame($pdfContent, file_get_contents($outputPath));
        } finally {
            /* Nettoyage du fichier PDF */
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * Verifie que la commande gere correctement une erreur d'authentification.
     * Quand le service retourne 401, la commande doit echouer proprement.
     */
    #[Test]
    public function testExecuteHandlesAuthenticationError(): void
    {
        $jsonFile = $this->createTempJsonFile();

        /* Mock de la reponse 401 (cle API invalide) */
        $unauthorizedResponse = $this->createMockResponse(401);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($unauthorizedResponse);

        $uploader = new ReportUploader($httpClient);
        $command = new UploadCommand($uploader);
        $tester = new CommandTester($command);

        $tester->execute([
            'json-file' => $jsonFile,
            '--api-key' => 'expired-key',
        ]);

        /* La commande doit echouer avec FAILURE */
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('authentification', $tester->getDisplay());
    }
}
