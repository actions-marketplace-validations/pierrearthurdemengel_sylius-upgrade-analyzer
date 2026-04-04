<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Plugin\PluginAlternativeSuggester;
use PierreArthur\SyliusUpgradeAnalyzer\Command\CompatibilityMatrixCommand;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\AddonsMarketplaceClient;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PackagistClient;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour la commande de matrice de compatibilite des plugins.
 * Verifie l'affichage en mode table et markdown, la gestion des arguments
 * et le nom de la commande.
 */
final class CompatibilityMatrixCommandTest extends TestCase
{
    /** Chemin vers le fichier de donnees des alternatives de plugins */
    private const DATA_FILE_PATH = __DIR__ . '/../../../data/plugin-alternatives.yaml';

    /**
     * Cree un mock du client HTTP retournant un statut UNKNOWN pour tous les plugins.
     * Le mock retourne un code 404 pour que les clients resolvents en UNKNOWN.
     */
    private function createMockHttpClient(): HttpClientInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $response->method('toArray')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        return $httpClient;
    }

    /**
     * Cree une instance de la commande avec des clients HTTP mockes.
     */
    private function createCommand(): CompatibilityMatrixCommand
    {
        $httpClient = $this->createMockHttpClient();

        return new CompatibilityMatrixCommand(
            addonsMarketplaceClient: new AddonsMarketplaceClient($httpClient),
            packagistClient: new PackagistClient($httpClient),
            alternativeSuggester: new PluginAlternativeSuggester(self::DATA_FILE_PATH),
        );
    }

    /**
     * Verifie que la commande s'execute et affiche la matrice.
     * Avec l'option --no-marketplace, aucune requete HTTP n'est effectuee.
     */
    #[Test]
    public function testExecuteShowsMatrix(): void
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'plugins' => 'bitbag/sylius-wishlist-plugin,bitbag/sylius-cms-plugin',
            '--no-marketplace' => true,
        ]);

        $output = $tester->getDisplay();

        /* Verification que les noms des plugins apparaissent dans la sortie */
        self::assertStringContainsString('bitbag/sylius-wishlist-plugin', $output, 'Le plugin wishlist devrait apparaitre dans la matrice.');
        self::assertStringContainsString('bitbag/sylius-cms-plugin', $output, 'Le plugin CMS devrait apparaitre dans la matrice.');

        /* Verification du code de retour */
        self::assertSame(0, $tester->getStatusCode(), 'La commande devrait retourner le code de succes.');
    }

    /**
     * Verifie que la commande accepte une liste de plugins en argument.
     * Les plugins sont passes sous forme de chaine separee par des virgules.
     */
    #[Test]
    public function testExecuteWithPluginsArgument(): void
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'plugins' => 'flux-se/sylius-payum-stripe-plugin',
            '--no-marketplace' => true,
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('flux-se/sylius-payum-stripe-plugin', $output, 'Le plugin Stripe devrait apparaitre dans la sortie.');
        self::assertSame(0, $tester->getStatusCode());
    }

    /**
     * Verifie que la commande supporte le format de sortie Markdown.
     * Le format markdown produit un tableau avec des separateurs '|'.
     */
    #[Test]
    public function testExecuteWithMarkdownFormat(): void
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'plugins' => 'bitbag/sylius-wishlist-plugin',
            '--format' => 'markdown',
            '--no-marketplace' => true,
        ]);

        $output = $tester->getDisplay();

        /* Verification des en-tetes Markdown */
        self::assertStringContainsString('| Plugin |', $output, 'L\'en-tete Markdown devrait etre present.');
        self::assertStringContainsString('|--------|', $output, 'Le separateur Markdown devrait etre present.');
        self::assertStringContainsString('bitbag/sylius-wishlist-plugin', $output);
        self::assertSame(0, $tester->getStatusCode());
    }

    /**
     * Verifie que getName retourne le nom attendu de la commande.
     * Le nom est defini via l'attribut AsCommand.
     */
    #[Test]
    public function testGetNameReturnsExpected(): void
    {
        $command = $this->createCommand();

        self::assertSame('sylius-upgrade:matrix', $command->getName());
    }
}
