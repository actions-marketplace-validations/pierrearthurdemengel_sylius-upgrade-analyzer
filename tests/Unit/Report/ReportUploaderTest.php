<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Report;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Exception\LicenseExpiredException;
use PierreArthur\SyliusUpgradeAnalyzer\Exception\ServiceUnavailableException;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Report\ReportUploader;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour le service d'envoi de rapports vers le service distant.
 * Utilise des mocks de HttpClientInterface et ResponseInterface.
 */
final class ReportUploaderTest extends TestCase
{
    /**
     * Cree un rapport de migration minimal pour les tests.
     */
    private function createMinimalReport(): MigrationReport
    {
        $report = new MigrationReport(
            startedAt: new \DateTimeImmutable('2025-01-15T10:00:00+00:00'),
            detectedSyliusVersion: '1.12.0',
            targetVersion: '2.0',
            projectPath: '/tmp/test-project',
        );

        $report->complete();

        return $report;
    }

    /**
     * Cree un mock de ResponseInterface avec le code HTTP et les donnees specifies.
     *
     * @param array<string, mixed>|null $responseData Donnees JSON de la reponse
     */
    private function createMockResponse(int $statusCode, ?array $responseData = null): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        if ($responseData !== null) {
            $response->method('toArray')->willReturn($responseData);
        }

        return $response;
    }

    /**
     * Verifie qu'un upload reussi retourne l'URL du PDF.
     * Le service retourne un code 200 avec une cle pdf_url dans la reponse JSON.
     */
    #[Test]
    public function testUploadSuccess(): void
    {
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/abc123.pdf';

        $response = $this->createMockResponse(200, ['pdf_url' => $pdfUrl]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $uploader = new ReportUploader($httpClient);
        $report = $this->createMinimalReport();

        /* L'upload doit retourner l'URL du PDF */
        $result = $uploader->upload($report, 'test-api-key');
        self::assertSame($pdfUrl, $result);
    }

    /**
     * Verifie qu'une cle API invalide ou expiree leve une LicenseExpiredException.
     * Le service retourne un code 401 quand la cle est invalide.
     */
    #[Test]
    public function testUploadExpiredKeyThrowsException(): void
    {
        $response = $this->createMockResponse(401);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $uploader = new ReportUploader($httpClient);
        $report = $this->createMinimalReport();

        /* Une reponse 401 doit lever LicenseExpiredException */
        $this->expectException(LicenseExpiredException::class);
        $uploader->upload($report, 'expired-api-key');
    }

    /**
     * Verifie qu'une erreur serveur leve une ServiceUnavailableException.
     * Le service retourne un code 500 quand il est indisponible.
     */
    #[Test]
    public function testUploadServiceUnavailableThrowsException(): void
    {
        $response = $this->createMockResponse(500);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $uploader = new ReportUploader($httpClient);
        $report = $this->createMinimalReport();

        /* Une reponse 500 doit lever ServiceUnavailableException */
        $this->expectException(ServiceUnavailableException::class);
        $uploader->upload($report, 'test-api-key');
    }

    /**
     * Verifie que l'upload envoie les bons en-tetes HTTP.
     * Le header X-Api-Key doit contenir la cle API et Content-Type doit etre application/json.
     */
    #[Test]
    public function testUploadSendsCorrectHeaders(): void
    {
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/abc123.pdf';
        $apiKey = 'my-secret-api-key';

        $response = $this->createMockResponse(200, ['pdf_url' => $pdfUrl]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.sylius-upgrade-analyzer.dev/v1/reports',
                $this->callback(function (array $options) use ($apiKey): bool {
                    /* Verification des en-tetes */
                    self::assertArrayHasKey('headers', $options);
                    self::assertSame($apiKey, $options['headers']['X-Api-Key']);
                    self::assertSame('application/json', $options['headers']['Content-Type']);

                    /* Verification de la presence du corps JSON */
                    self::assertArrayHasKey('body', $options);
                    self::assertIsString($options['body']);

                    /* Verification que le corps est du JSON valide */
                    $decoded = json_decode($options['body'], true);
                    self::assertIsArray($decoded);

                    return true;
                }),
            )
            ->willReturn($response);

        $uploader = new ReportUploader($httpClient);
        $report = $this->createMinimalReport();

        $uploader->upload($report, $apiKey);
    }

    /**
     * Verifie le telechargement reussi d'un PDF.
     * Le service retourne un code 200 avec le contenu binaire du PDF.
     */
    #[Test]
    public function testDownloadPdfSuccess(): void
    {
        $pdfContent = '%PDF-1.4 fake pdf content';
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/abc123.pdf';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn($pdfContent);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->with('GET', $pdfUrl, $this->anything())->willReturn($response);

        $uploader = new ReportUploader($httpClient);

        /* Telechargement dans un fichier temporaire */
        $outputPath = sys_get_temp_dir() . '/sylius-test-download-' . uniqid() . '.pdf';

        try {
            $uploader->downloadPdf($pdfUrl, $outputPath);

            /* Verification que le fichier a ete cree avec le bon contenu */
            self::assertFileExists($outputPath);
            self::assertSame($pdfContent, file_get_contents($outputPath));
        } finally {
            /* Nettoyage du fichier temporaire */
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * Verifie qu'un echec de telechargement leve une ServiceUnavailableException.
     * Une reponse HTTP 404 lors du telechargement doit lever l'exception.
     */
    #[Test]
    public function testDownloadPdfFailureThrowsException(): void
    {
        $pdfUrl = 'https://api.sylius-upgrade-analyzer.dev/reports/not-found.pdf';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $uploader = new ReportUploader($httpClient);

        $outputPath = sys_get_temp_dir() . '/sylius-test-download-fail-' . uniqid() . '.pdf';

        /* Une reponse 404 doit lever ServiceUnavailableException */
        $this->expectException(ServiceUnavailableException::class);
        $uploader->downloadPdf($pdfUrl, $outputPath);
    }

    /**
     * Verifie qu'une reponse 403 (licence expiree) leve aussi LicenseExpiredException.
     * Le code 403 est traite de la meme maniere que le 401.
     */
    #[Test]
    public function testUploadForbiddenThrowsLicenseException(): void
    {
        $response = $this->createMockResponse(403);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $uploader = new ReportUploader($httpClient);
        $report = $this->createMinimalReport();

        /* Une reponse 403 doit lever LicenseExpiredException */
        $this->expectException(LicenseExpiredException::class);
        $uploader->upload($report, 'forbidden-api-key');
    }
}
