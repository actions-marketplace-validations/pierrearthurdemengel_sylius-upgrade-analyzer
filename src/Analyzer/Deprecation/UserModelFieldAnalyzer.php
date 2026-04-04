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
 * Analyseur des champs et methodes deprecies du modele User.
 * Sylius 2.0 supprime les champs locked, expiresAt, credentialsExpireAt
 * et l'interface Serializable des entites utilisateur.
 */
final class UserModelFieldAnalyzer implements AnalyzerInterface
{
    /** Estimation en minutes par champ/methode depreciee detecte */
    private const MINUTES_PER_FIELD = 60;

    /**
     * Noms de proprietes et methodes depreciees a rechercher.
     * Chaque entree contient le motif regex et la description associee.
     *
     * @var array<string, string>
     */
    private const DEPRECATED_PATTERNS = [
        'locked' => 'Propriete ou variable $locked',
        'isLocked' => 'Methode isLocked()',
        'setLocked' => 'Methode setLocked()',
        'expiresAt' => 'Propriete ou variable $expiresAt',
        'getExpiresAt' => 'Methode getExpiresAt()',
        'setExpiresAt' => 'Methode setExpiresAt()',
        'isExpired' => 'Methode isExpired()',
        'credentialsExpireAt' => 'Propriete ou variable $credentialsExpireAt',
        'getCredentialsExpireAt' => 'Methode getCredentialsExpireAt()',
        'setCredentialsExpireAt' => 'Methode setCredentialsExpireAt()',
        'isCredentialsExpired' => 'Methode isCredentialsExpired()',
        'getSalt' => 'Methode getSalt()',
    ];

    public function getName(): string
    {
        return 'User Model Field';
    }

    public function supports(MigrationReport $report): bool
    {
        $entityDir = $report->getProjectPath() . '/src/Entity';

        return is_dir($entityDir);
    }

    public function analyze(MigrationReport $report): void
    {
        $entityDir = $report->getProjectPath() . '/src/Entity';
        if (!is_dir($entityDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($entityDir)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = (string) file_get_contents($filePath);

            /* Detection des proprietes et methodes depreciees */
            $this->detectDeprecatedFields($report, $content, $filePath, $file->getRelativePathname());

            /* Detection de l'interface Serializable */
            $this->detectSerializableInterface($report, $content, $filePath, $file->getRelativePathname());
        }
    }

    /**
     * Detecte les proprietes et methodes depreciees dans le contenu d'un fichier PHP.
     */
    private function detectDeprecatedFields(
        MigrationReport $report,
        string $content,
        string $filePath,
        string $relativePath,
    ): void {
        $lines = explode("\n", $content);

        foreach (self::DEPRECATED_PATTERNS as $pattern => $description) {
            foreach ($lines as $index => $line) {
                /* Detection des proprietes : $locked, $expiresAt, etc. */
                if ($this->isPropertyDeclaration($line, $pattern)) {
                    $lineNumber = $index + 1;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf('%s detecte(e) dans %s', $description, $relativePath),
                        detail: sprintf(
                            'Le champ ou la methode "%s" en ligne %d de %s est deprecie(e) dans Sylius 2.0. '
                            . 'Les champs de verrouillage et d\'expiration sont supprimes du modele User.',
                            $pattern,
                            $lineNumber,
                            $relativePath,
                        ),
                        suggestion: $this->getSuggestionForPattern($pattern),
                        file: $filePath,
                        line: $lineNumber,
                        estimatedMinutes: self::MINUTES_PER_FIELD,
                    ));
                    /* Ne rapporter qu'une seule occurrence par pattern et par fichier */
                    break;
                }

                /* Detection des methodes : isLocked(), getSalt(), etc. */
                if ($this->isMethodDeclaration($line, $pattern)) {
                    $lineNumber = $index + 1;
                    $report->addIssue(new MigrationIssue(
                        severity: Severity::BREAKING,
                        category: Category::DEPRECATION,
                        analyzer: $this->getName(),
                        message: sprintf('%s detecte(e) dans %s', $description, $relativePath),
                        detail: sprintf(
                            'Le champ ou la methode "%s" en ligne %d de %s est deprecie(e) dans Sylius 2.0. '
                            . 'Les champs de verrouillage et d\'expiration sont supprimes du modele User.',
                            $pattern,
                            $lineNumber,
                            $relativePath,
                        ),
                        suggestion: $this->getSuggestionForPattern($pattern),
                        file: $filePath,
                        line: $lineNumber,
                        estimatedMinutes: self::MINUTES_PER_FIELD,
                    ));
                    /* Ne rapporter qu'une seule occurrence par pattern et par fichier */
                    break;
                }
            }
        }
    }

    /**
     * Detecte l'implementation de l'interface Serializable dans le contenu d'un fichier PHP.
     */
    private function detectSerializableInterface(
        MigrationReport $report,
        string $content,
        string $filePath,
        string $relativePath,
    ): void {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            /* Recherche de "implements" contenant "Serializable" */
            if (preg_match('/\bimplements\b.*\bSerializable\b/', $line) === 1) {
                $lineNumber = $index + 1;
                $report->addIssue(new MigrationIssue(
                    severity: Severity::BREAKING,
                    category: Category::DEPRECATION,
                    analyzer: $this->getName(),
                    message: sprintf('Interface Serializable detectee dans %s', $relativePath),
                    detail: sprintf(
                        'L\'interface \\Serializable est implementee en ligne %d de %s. '
                        . 'Dans Sylius 2.0, les entites utilisateur n\'implementent plus cette interface. '
                        . 'Utiliser __serialize() et __unserialize() a la place.',
                        $lineNumber,
                        $relativePath,
                    ),
                    suggestion: 'Remplacer l\'implementation de \\Serializable par les methodes magiques '
                        . '__serialize(): array et __unserialize(array $data): void.',
                    file: $filePath,
                    line: $lineNumber,
                    estimatedMinutes: self::MINUTES_PER_FIELD,
                ));

                return;
            }
        }
    }

    /**
     * Determine si une ligne contient une declaration de propriete pour le pattern donne.
     */
    private function isPropertyDeclaration(string $line, string $pattern): bool
    {
        /* Recherche de la forme "$pattern" comme propriete (ex: $locked, $expiresAt) */
        if (preg_match('/\$' . preg_quote($pattern, '/') . '\b/', $line) === 1) {
            /* Verifier que c'est une declaration de propriete (private, protected, public) */
            if (preg_match('/^\s*(private|protected|public)\s+/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine si une ligne contient une declaration de methode pour le pattern donne.
     */
    private function isMethodDeclaration(string $line, string $pattern): bool
    {
        /* Recherche de la forme "function pattern(" comme methode */
        return preg_match('/\bfunction\s+' . preg_quote($pattern, '/') . '\s*\(/', $line) === 1;
    }

    /**
     * Retourne la suggestion de correction pour un pattern donne.
     */
    private function getSuggestionForPattern(string $pattern): string
    {
        return match (true) {
            str_contains($pattern, 'locked') || str_contains($pattern, 'Locked')
                => 'Supprimer la propriete et les methodes liees au verrouillage. '
                    . 'Utiliser un systeme de ban personnalise si necessaire.',
            str_contains($pattern, 'xpires') || str_contains($pattern, 'xpired')
                => 'Supprimer la propriete et les methodes liees a l\'expiration. '
                    . 'Gerer l\'expiration des comptes via un mecanisme personnalise si necessaire.',
            str_contains($pattern, 'credentials') || str_contains($pattern, 'Credentials')
                => 'Supprimer la propriete et les methodes liees a l\'expiration des identifiants. '
                    . 'Gerer le renouvellement des mots de passe via un mecanisme personnalise.',
            $pattern === 'getSalt'
                => 'Supprimer la methode getSalt() ou la faire retourner null. '
                    . 'Les algorithmes modernes (bcrypt, argon2) gerent le sel automatiquement.',
            default => 'Supprimer ou adapter ce champ/methode pour Sylius 2.0.',
        };
    }
}
