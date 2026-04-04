<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Command;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Plugin\PluginAlternativeSuggester;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\AddonsMarketplaceClient;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PackagistClient;
use PierreArthur\SyliusUpgradeAnalyzer\Marketplace\PluginCompatibilityStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande affichant la matrice de compatibilite des plugins Sylius.
 * Interroge les clients Marketplace et Packagist pour determiner
 * la compatibilite de chaque plugin avec la version cible.
 */
#[AsCommand(
    name: 'sylius-upgrade:matrix',
    description: 'Affiche la matrice de compatibilite des plugins Sylius avec la version cible',
)]
final class CompatibilityMatrixCommand extends Command
{
    public function __construct(
        private readonly AddonsMarketplaceClient $addonsMarketplaceClient,
        private readonly PackagistClient $packagistClient,
        private readonly PluginAlternativeSuggester $alternativeSuggester,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'plugins',
                InputArgument::OPTIONAL,
                'Liste de plugins separes par des virgules (ou lecture depuis composer.json si omis)',
            )
            ->addOption(
                'target-version',
                't',
                InputOption::VALUE_REQUIRED,
                'Version cible de Sylius pour la migration',
                '2.2',
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Format de sortie : table ou markdown',
                'table',
            )
            ->addOption(
                'no-marketplace',
                null,
                InputOption::VALUE_NONE,
                'Desactiver les requetes vers les API externes (mode hors-ligne)',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetVersion = $input->getOption('target-version');
        $format = $input->getOption('format');
        $noMarketplace = $input->getOption('no-marketplace');

        /* Recuperation de la liste des plugins */
        $plugins = $this->resolvePlugins($input, $io);
        if (count($plugins) === 0) {
            $io->warning('Aucun plugin Sylius detecte.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Analyse de compatibilite pour <info>%d</info> plugins (cible : Sylius %s)', count($plugins), $targetVersion));
        $io->newLine();

        /* Construction de la matrice de compatibilite */
        $rows = [];
        foreach ($plugins as $packageName) {
            $status = $this->resolveStatus($packageName, $targetVersion, $noMarketplace);
            $alternative = $this->alternativeSuggester->suggest($packageName);

            $statusLabel = $this->formatStatus($status);
            $alternativeLabel = $alternative !== null
                ? ($alternative['replacement'] ?? 'Reimplementation')
                : '-';
            $hoursLabel = $alternative !== null
                ? sprintf('%dh', $alternative['migration_hours'])
                : '-';

            $rows[] = [
                $packageName,
                $statusLabel,
                $alternativeLabel,
                $hoursLabel,
            ];
        }

        /* Affichage selon le format demande */
        if ($format === 'markdown') {
            $this->renderMarkdown($output, $rows);
        } else {
            $this->renderTable($output, $rows);
        }

        return Command::SUCCESS;
    }

    /**
     * Resout la liste des plugins depuis l'argument ou depuis composer.json.
     *
     * @return list<string>
     */
    private function resolvePlugins(InputInterface $input, SymfonyStyle $io): array
    {
        $pluginsArg = $input->getArgument('plugins');

        if ($pluginsArg !== null && is_string($pluginsArg) && $pluginsArg !== '') {
            /* Liste fournie en argument, separee par des virgules */
            $plugins = array_map('trim', explode(',', $pluginsArg));

            return array_values(array_filter($plugins, static fn (string $p): bool => $p !== ''));
        }

        /* Lecture depuis composer.json du repertoire courant */
        $composerJsonPath = getcwd() . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            $io->note('Aucun argument fourni et pas de composer.json dans le repertoire courant.');

            return [];
        }

        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($composerData)) {
            return [];
        }

        $plugins = [];
        $sections = ['require', 'require-dev'];

        foreach ($sections as $section) {
            $dependencies = $composerData[$section] ?? [];
            if (!is_array($dependencies)) {
                continue;
            }

            foreach (array_keys($dependencies) as $packageName) {
                if (!is_string($packageName)) {
                    continue;
                }

                if ($this->isSyliusPlugin($packageName)) {
                    $plugins[] = $packageName;
                }
            }
        }

        return $plugins;
    }

    /**
     * Determine si un nom de paquet correspond a un plugin Sylius.
     * Exclut les paquets du coeur Sylius.
     */
    private function isSyliusPlugin(string $packageName): bool
    {
        $parts = explode('/', $packageName);
        if (count($parts) !== 2) {
            return false;
        }

        [$vendor, $package] = $parts;

        /* Exclusion des paquets du coeur Sylius */
        if ($vendor === 'sylius' && !str_contains($package, 'plugin')) {
            return false;
        }

        $lowerVendor = strtolower($vendor);
        $lowerPackage = strtolower($package);

        return str_contains($lowerVendor, 'sylius') || str_contains($lowerPackage, 'sylius');
    }

    /**
     * Determine le statut de compatibilite d'un plugin.
     * En mode hors-ligne, retourne directement UNKNOWN.
     */
    private function resolveStatus(string $packageName, string $targetVersion, bool $noMarketplace): PluginCompatibilityStatus
    {
        if ($noMarketplace) {
            /* En mode hors-ligne, on verifie si une alternative est connue */
            $alternative = $this->alternativeSuggester->suggest($packageName);
            if ($alternative !== null) {
                if ($alternative['replacement'] === null) {
                    return PluginCompatibilityStatus::INCOMPATIBLE;
                }

                if ($alternative['replacement'] === $packageName) {
                    return PluginCompatibilityStatus::COMPATIBLE;
                }

                return PluginCompatibilityStatus::ABANDONED;
            }

            return PluginCompatibilityStatus::UNKNOWN;
        }

        /* Tentative via Addons Marketplace */
        $result = $this->addonsMarketplaceClient->checkCompatibility($packageName, $targetVersion);
        if ($result->status !== PluginCompatibilityStatus::UNKNOWN) {
            return $result->status;
        }

        /* Repli sur Packagist */
        $result = $this->packagistClient->checkCompatibility($packageName, $targetVersion);

        return $result->status;
    }

    /**
     * Formate un statut de compatibilite en libelle lisible avec couleur.
     */
    private function formatStatus(PluginCompatibilityStatus $status): string
    {
        return match ($status) {
            PluginCompatibilityStatus::COMPATIBLE => '<fg=green>Compatible</>',
            PluginCompatibilityStatus::INCOMPATIBLE => '<fg=red>Incompatible</>',
            PluginCompatibilityStatus::PARTIALLY_COMPATIBLE => '<fg=yellow>Partiel</>',
            PluginCompatibilityStatus::UNKNOWN => '<fg=gray>Inconnu</>',
            PluginCompatibilityStatus::ABANDONED => '<fg=red>Abandonne</>',
        };
    }

    /**
     * Affiche la matrice sous forme de tableau Symfony Console.
     *
     * @param list<array{string, string, string, string}> $rows
     */
    private function renderTable(OutputInterface $output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Plugin', 'Statut', 'Alternative', 'Migration']);
        $table->setRows($rows);
        $table->setStyle('box');
        $table->render();
    }

    /**
     * Affiche la matrice au format Markdown.
     *
     * @param list<array{string, string, string, string}> $rows
     */
    private function renderMarkdown(OutputInterface $output, array $rows): void
    {
        $output->writeln('| Plugin | Statut | Alternative | Migration |');
        $output->writeln('|--------|--------|-------------|-----------|');

        foreach ($rows as $row) {
            /* Suppression des balises de couleur pour le Markdown */
            $cleanStatus = strip_tags($row[1]);
            $output->writeln(sprintf(
                '| %s | %s | %s | %s |',
                $row[0],
                $cleanStatus,
                $row[2],
                $row[3],
            ));
        }
    }
}
