<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Report;

use PierreArthur\SyliusUpgradeAnalyzer\Exception\LicenseExpiredException;
use PierreArthur\SyliusUpgradeAnalyzer\Exception\ServiceUnavailableException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP centralisé pour les appels vers le service sylius-upgrade-analyzer.
 * Gère l'authentification par clé API et le traitement des erreurs HTTP.
 */
final class ApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl = 'https://sylius-upgrade-analyzer-api.fly.dev',
    ) {
    }

    /**
     * Envoie un rapport JSON pour génération PDF.
     *
     * @param array<string, mixed> $reportData Données du rapport sérialisé
     * @param string               $apiKey     Clé API pour l'authentification
     *
     * @return array<string, mixed>
     *
     * @throws LicenseExpiredException    Si la clé API est invalide ou expirée
     * @throws ServiceUnavailableException Si le service est indisponible
     */
    public function uploadReport(array $reportData, string $apiKey): array
    {
        return $this->post('/v1/reports', $reportData, $apiKey);
    }

    /**
     * Envoie plusieurs rapports pour génération d'un PDF consolidé (Agency).
     *
     * @param array<string, mixed> $payload Données multi-projets
     * @param string               $apiKey  Clé API Agency
     *
     * @return array<string, mixed>
     */
    public function uploadMultiReport(array $payload, string $apiKey): array
    {
        return $this->post('/v1/reports/multi', $payload, $apiKey);
    }

    /**
     * Compare deux rapports côté serveur (Agency).
     *
     * @param string $beforeId ID du rapport avant
     * @param string $afterId  ID du rapport après
     * @param string $apiKey   Clé API Agency
     *
     * @return array<string, mixed> Résultat de la comparaison
     */
    public function compareReports(string $beforeId, string $afterId, string $apiKey): array
    {
        return $this->post('/v1/reports/compare', [
            'before_id' => $beforeId,
            'after_id' => $afterId,
        ], $apiKey);
    }

    /**
     * Récupère l'historique des rapports (Agency).
     *
     * @param string $apiKey Clé API
     * @param int    $limit  Nombre de résultats
     * @param int    $offset Décalage pour la pagination
     *
     * @return array<string, mixed> Historique paginé
     */
    public function fetchHistory(string $apiKey, int $limit = 20, int $offset = 0): array
    {
        return $this->get(sprintf('/v1/reports/history?limit=%d&offset=%d', $limit, $offset), $apiKey);
    }

    /**
     * Récupère la configuration webhook actuelle (Agency).
     *
     * @return array<string, mixed>
     */
    public function getWebhook(string $apiKey): array
    {
        return $this->get('/v1/settings/webhook', $apiKey);
    }

    /**
     * Configure un webhook (Agency).
     *
     * @param array<string, mixed> $config Configuration du webhook (url, secret, events)
     *
     * @return array<string, mixed>
     */
    public function setWebhook(array $config, string $apiKey): array
    {
        return $this->request('PUT', '/v1/settings/webhook', $config, $apiKey);
    }

    /**
     * Supprime la configuration webhook (Agency).
     *
     * @return array<string, mixed>
     */
    public function deleteWebhook(string $apiKey): array
    {
        return $this->request('DELETE', '/v1/settings/webhook', null, $apiKey);
    }

    /**
     * Télécharge un fichier depuis une URL et le sauvegarde sur le disque.
     *
     * @throws ServiceUnavailableException Si le téléchargement échoue
     */
    public function downloadFile(string $url, string $outputPath): void
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 60]);

            if ($response->getStatusCode() >= 400) {
                throw new ServiceUnavailableException(
                    sprintf('Échec du téléchargement (HTTP %d).', $response->getStatusCode()),
                );
            }

            $content = $response->getContent();
        } catch (ServiceUnavailableException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ServiceUnavailableException(
                sprintf('Erreur lors du téléchargement : %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($outputPath, $content) === false) {
            throw new ServiceUnavailableException(
                sprintf('Impossible d\'écrire le fichier dans %s.', $outputPath),
            );
        }
    }

    /**
     * Effectue une requête GET authentifiée.
     *
     * @return array<string, mixed>
     */
    private function get(string $endpoint, string $apiKey): array
    {
        return $this->request('GET', $endpoint, null, $apiKey);
    }

    /**
     * Effectue une requête POST authentifiée avec un body JSON.
     *
     * @param array<string, mixed> $data Corps de la requête
     *
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $data, string $apiKey): array
    {
        return $this->request('POST', $endpoint, $data, $apiKey);
    }

    /**
     * Effectue une requête HTTP authentifiée vers le service.
     *
     * @param array<string, mixed>|null $data Corps de la requête (null pour GET/DELETE)
     *
     * @return array<string, mixed>
     *
     * @throws LicenseExpiredException    Si la clé API est invalide ou expirée (401/403)
     * @throws ServiceUnavailableException Si le service est indisponible (5xx ou erreur réseau)
     */
    private function request(string $method, string $endpoint, ?array $data, string $apiKey): array
    {
        $options = [
            'headers' => [
                'X-Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ];

        if ($data !== null) {
            $json = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new ServiceUnavailableException('Impossible de sérialiser les données en JSON.');
            }
            $options['body'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $endpoint, $options);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $exception) {
            throw new ServiceUnavailableException(
                sprintf('Erreur réseau : %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        if ($statusCode === 401 || $statusCode === 403) {
            throw new LicenseExpiredException(
                'Clé API invalide ou licence expirée. Vérifiez votre clé API.',
            );
        }

        if ($statusCode >= 500) {
            throw new ServiceUnavailableException(
                sprintf('Le service a retourné une erreur %d.', $statusCode),
            );
        }

        try {
            return $response->toArray();
        } catch (\Throwable $exception) {
            throw new ServiceUnavailableException(
                sprintf('Réponse invalide du service : %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}
