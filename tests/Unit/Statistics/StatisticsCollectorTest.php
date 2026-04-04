<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use PierreArthur\SyliusUpgradeAnalyzer\Statistics\StatisticsCollector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests unitaires pour le collecteur de statistiques anonymes.
 * Verifie la construction des donnees, l'envoi via HTTP
 * et la gestion de l'opt-in dans composer.json.
 */
final class StatisticsCollectorTest extends TestCase
{
    /** Repertoire temporaire utilise pour les tests */
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sylius-stats-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        /* Nettoyage du repertoire temporaire */
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Supprime recursivement un repertoire et son contenu.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }

    /**
     * Cree un rapport de migration avec quelques issues pour les tests.
     */
    private function createReportWithIssues(): MigrationReport
    {
        /* Creation d'un composer.json minimal dans le repertoire temporaire */
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => ['php' => '>=8.2'],
        ]));

        $report = new MigrationReport(
            startedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
            detectedSyliusVersion: '1.12.0',
            targetVersion: '2.2',
            projectPath: $this->tempDir,
        );

        /* Ajout de quelques issues de test */
        $report->addIssue(new MigrationIssue(
            severity: Severity::BREAKING,
            category: Category::TWIG,
            analyzer: 'TestAnalyzer',
            message: 'Template Twig deprecie',
            detail: 'Detail du probleme',
            suggestion: 'Migrer vers le nouveau systeme',
            estimatedMinutes: 60,
        ));

        $report->addIssue(new MigrationIssue(
            severity: Severity::WARNING,
            category: Category::DEPRECATION,
            analyzer: 'TestAnalyzer',
            message: 'Methode depreciee',
            detail: 'Detail du probleme',
            suggestion: 'Utiliser la nouvelle methode',
            estimatedMinutes: 30,
        ));

        $report->addIssue(new MigrationIssue(
            severity: Severity::SUGGESTION,
            category: Category::PLUGIN,
            analyzer: 'TestAnalyzer',
            message: 'Plugin compatible disponible',
            detail: 'Detail',
            suggestion: 'Mettre a jour le plugin',
        ));

        $report->complete();

        return $report;
    }

    /**
     * Verifie que collect construit correctement les donnees statistiques anonymes.
     * Le tableau retourne doit contenir toutes les cles attendues sans
     * information identifiante.
     */
    #[Test]
    public function testCollectBuildsAnonymousData(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $collector = new StatisticsCollector($httpClient);

        $report = $this->createReportWithIssues();
        $data = $collector->collect($report);

        /* Verification de la presence des cles obligatoires */
        self::assertArrayHasKey('complexity', $data, 'Les donnees doivent contenir la complexite.');
        self::assertArrayHasKey('php_version', $data, 'Les donnees doivent contenir la version PHP.');
        self::assertArrayHasKey('total_issues', $data, 'Les donnees doivent contenir le nombre total d\'issues.');
        self::assertArrayHasKey('total_hours', $data, 'Les donnees doivent contenir le total d\'heures estimees.');
        self::assertArrayHasKey('breaking_count', $data, 'Les donnees doivent contenir le compte des breaking.');
        self::assertArrayHasKey('warning_count', $data, 'Les donnees doivent contenir le compte des warnings.');
        self::assertArrayHasKey('suggestion_count', $data, 'Les donnees doivent contenir le compte des suggestions.');
        self::assertArrayHasKey('sylius_version', $data, 'Les donnees doivent contenir la version Sylius detectee.');
        self::assertArrayHasKey('target_version', $data, 'Les donnees doivent contenir la version cible.');
        self::assertArrayHasKey('tool_version', $data, 'Les donnees doivent contenir la version de l\'outil.');
        self::assertArrayHasKey('timestamp', $data, 'Les donnees doivent contenir un horodatage.');
        self::assertArrayHasKey('category_counts', $data, 'Les donnees doivent contenir les compteurs par categorie.');

        /* Verification des valeurs */
        self::assertSame(3, $data['total_issues']);
        self::assertSame(1, $data['breaking_count']);
        self::assertSame(1, $data['warning_count']);
        self::assertSame(1, $data['suggestion_count']);
        self::assertSame('1.12.0', $data['sylius_version']);
        self::assertSame('2.2', $data['target_version']);
    }

    /**
     * Verifie que send effectue une requete POST vers le bon endpoint.
     * Le client HTTP mock doit etre appele avec la methode POST et l'URL attendue.
     */
    #[Test]
    public function testSendPostsToEndpoint(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        /* Verification que la requete POST est effectuee avec les bons parametres */
        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://stats.sylius-upgrade-analyzer.dev/collect',
                self::callback(function (array $options): bool {
                    return isset($options['json']) && is_array($options['json']);
                }),
            );

        $collector = new StatisticsCollector($httpClient);

        $data = ['complexity' => 'moderate', 'total_issues' => 5];
        $collector->send($data);
    }

    /**
     * Verifie que isOptedIn retourne false par defaut.
     * Un composer.json sans section telemetry indique que l'utilisateur
     * n'a pas active la collecte de statistiques.
     */
    #[Test]
    public function testIsOptedInReturnsFalseByDefault(): void
    {
        /* Creation d'un composer.json sans section telemetry */
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'require' => ['php' => '>=8.2'],
        ]));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $collector = new StatisticsCollector($httpClient);

        self::assertFalse(
            $collector->isOptedIn($this->tempDir),
            'L\'opt-in devrait etre desactive par defaut.',
        );
    }

    /**
     * Verifie que isOptedIn retourne true quand la telemetrie est activee.
     * La section extra.sylius-upgrade-analyzer.telemetry doit etre a true.
     */
    #[Test]
    public function testIsOptedInReturnsTrueWhenEnabled(): void
    {
        /* Creation d'un composer.json avec telemetrie activee */
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'require' => ['php' => '>=8.2'],
            'extra' => [
                'sylius-upgrade-analyzer' => [
                    'telemetry' => true,
                ],
            ],
        ]));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $collector = new StatisticsCollector($httpClient);

        self::assertTrue(
            $collector->isOptedIn($this->tempDir),
            'L\'opt-in devrait etre active quand telemetry est a true dans composer.json.',
        );
    }
}
