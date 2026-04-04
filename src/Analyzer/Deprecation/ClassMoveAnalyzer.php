<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\Analyzer\Deprecation;

use PierreArthur\SyliusUpgradeAnalyzer\Analyzer\AnalyzerInterface;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Category;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationReport;
use PierreArthur\SyliusUpgradeAnalyzer\Model\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Analyseur des deplacements de classes entre bundles dans Sylius 2.0.
 * Plusieurs classes ont ete deplacees d'un bundle a un autre.
 * Cet analyseur detecte les usages des anciens FQCN dans les fichiers PHP du projet.
 */
final class ClassMoveAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par classe deplacee */
    private const MINUTES_PER_MOVE = 30;

    /** URL de la documentation de migration */
    private const DOC_URL = 'https://docs.sylius.com/migration-2.0';

    /**
     * Correspondance entre les anciens et les nouveaux FQCN.
     *
     * @var array<string, string>
     */
    private const CLASS_MOVES = [
        'Sylius\Bundle\ShopBundle\EmailManager\ContactEmailManager' => 'Sylius\Bundle\CoreBundle\Mailer\ContactEmailManager',
        'Sylius\Bundle\AdminBundle\EmailManager\ShipmentEmailManager' => 'Sylius\Bundle\CoreBundle\Mailer\ShipmentEmailManager',
        'Sylius\Bundle\AdminBundle\EmailManager\ShipmentEmailManagerInterface' => 'Sylius\Bundle\CoreBundle\Mailer\ShipmentEmailManagerInterface',
        'Sylius\Bundle\CoreBundle\Theme\ChannelBasedThemeContext' => 'Sylius\Bundle\ShopBundle\Theme\ChannelBasedThemeContext',
        'Sylius\Component\Promotion\Checker\Rule\ItemTotalRuleChecker' => 'Sylius\Component\Core\Promotion\Checker\Rule\ItemTotalRuleChecker',
        'Sylius\Bundle\PayumBundle\Validator\GatewayFactoryExistsValidator' => 'Sylius\Bundle\PaymentBundle\Validator\Constraints\GatewayFactoryExistsValidator',
        'Sylius\Bundle\PayumBundle\Validator\GroupsGenerator\GatewayConfigGroupsGenerator' => 'Sylius\Bundle\PaymentBundle\Validator\Constraints\GatewayConfigGroupsGenerator',
        'Sylius\Bundle\UiBundle\Storage\FilterStorageInterface' => 'Sylius\Bundle\GridBundle\Storage\FilterStorageInterface',
        'Sylius\Bundle\UiBundle\Storage\FilterStorage' => 'Sylius\Bundle\GridBundle\Storage\FilterStorage',
        'Sylius\Bundle\ApiBundle\CommandHandler\Account\ResendVerificationEmailHandler' => 'RequestShopUserVerificationHandler',
        'Sylius\Bundle\ApiBundle\CommandHandler\Account\SendAccountVerificationEmailHandler' => 'SendShopUserVerificationEmailHandler',
        'Sylius\Bundle\ApiBundle\CommandHandler\Account\VerifyCustomerAccountHandler' => 'VerifyShopUserHandler',
        'Sylius\Bundle\ApiBundle\Command\Account\VerifyCustomerAccount' => 'VerifyShopUser',
        'Sylius\Bundle\ApiBundle\Command\Account\ResendVerificationEmail' => 'RequestShopUserVerification',
    ];

    public function getName(): string
    {
        return 'Class Move';
    }

    public function supports(MigrationReport $report): bool
    {
        $srcDir = $report->getProjectPath() . '/src';
        if (!is_dir($srcDir)) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        foreach ($finder as $file) {
            $content = (string) file_get_contents((string) $file->getRealPath());
            foreach (array_keys(self::CLASS_MOVES) as $oldFqcn) {
                if (str_contains($content, $oldFqcn)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function analyze(MigrationReport $report): void
    {
        $projectPath = $report->getProjectPath();
        $srcDir = $projectPath . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($srcDir)->name('*.php');

        $moveCount = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach (self::CLASS_MOVES as $oldFqcn => $newFqcn) {
                /* Recherche de l'ancien FQCN dans les statements use */
                foreach ($lines as $index => $line) {
                    if (!str_contains($line, 'use ') || !str_contains($line, $oldFqcn)) {
                        continue;
                    }

                    $lineNumber = $index + 1;
                    $moveCount++;

                    $oldShortName = $this->getShortName($oldFqcn);

                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf(
                            'Classe deplacee %s detectee dans %s',
                            $oldShortName,
                            $file->getRelativePathname(),
                        ),
                        detail: sprintf(
                            'Le fichier %s utilise l\'ancien FQCN %s (ligne %d). '
                            . 'Cette classe a ete deplacee vers %s dans Sylius 2.0.',
                            $file->getRelativePathname(),
                            $oldFqcn,
                            $lineNumber,
                            $newFqcn,
                        ),
                        suggestion: sprintf(
                            'Remplacer le use statement de %s par %s.',
                            $oldFqcn,
                            $newFqcn,
                        ),
                        file: $filePath,
                        line: $lineNumber,
                        docUrl: self::DOC_URL,
                        estimatedMinutes: self::MINUTES_PER_MOVE,
                    ));
                }
            }
        }

        /* Resume global */
        if ($moveCount > 0) {
            $report->addIssue(new MigrationIssue(
                severity: Severity::BREAKING,
                category: Category::DEPRECATION,
                analyzer: $this->getName(),
                message: sprintf(
                    '%d usage(s) de classes deplacees detecte(s)',
                    $moveCount,
                ),
                detail: 'Plusieurs classes ont ete deplacees entre bundles dans Sylius 2.0. '
                    . 'Les anciens FQCN ne sont plus valides et doivent etre remplaces.',
                suggestion: 'Mettre a jour les use statements pour pointer vers les nouveaux FQCN '
                    . 'selon la documentation de migration Sylius 2.0.',
                docUrl: self::DOC_URL,
                estimatedMinutes: $moveCount * self::MINUTES_PER_MOVE,
            ));
        }
    }

    /**
     * Extrait le nom court d'un FQCN.
     */
    private function getShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
