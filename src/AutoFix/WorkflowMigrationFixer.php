<?php

declare(strict_types=1);

namespace PierreArthur\SyliusUpgradeAnalyzer\AutoFix;

use PierreArthur\SyliusUpgradeAnalyzer\Model\MigrationIssue;
use Symfony\Component\Yaml\Yaml;

/**
 * Fixer pour la migration des machines a etats winzou vers Symfony Workflow.
 * Lit la configuration winzou YAML et genere la configuration equivalente
 * au format Symfony Workflow avec les etats, transitions et callbacks de base.
 */
final class WorkflowMigrationFixer implements AutoFixInterface
{
    /** Nom de l'analyseur cible */
    private const TARGET_ANALYZER = 'Winzou State Machine';

    /** Motif de detection des issues avec reference a un fichier YAML */
    private const YAML_ISSUE_PATTERN = '/Machine a etats winzou "(.+)" a migrer/';

    public function getName(): string
    {
        return 'Workflow Migration Fixer';
    }

    public function supports(MigrationIssue $issue): bool
    {
        /* Seules les issues de WinzouStateMachineAnalyzer avec un fichier YAML sont supportees */
        if ($issue->getAnalyzer() !== self::TARGET_ANALYZER) {
            return false;
        }

        /* Verification que l'issue reference un fichier de configuration YAML */
        $file = $issue->getFile();
        if ($file === null) {
            return false;
        }

        return (bool) preg_match('/\.(yaml|yml)$/i', $file);
    }

    public function fix(MigrationIssue $issue, string $projectPath): ?MigrationFix
    {
        $filePath = $issue->getFile();
        if ($filePath === null) {
            return null;
        }

        /* Extraction du nom de la machine a etats depuis le message */
        if (!preg_match(self::YAML_ISSUE_PATTERN, $issue->getMessage(), $matches)) {
            return null;
        }

        $stateMachineName = $matches[1];

        /* Lecture du fichier YAML source */
        $absolutePath = $this->resolveAbsolutePath($filePath, $projectPath);
        if (!file_exists($absolutePath)) {
            return null;
        }

        try {
            $config = Yaml::parseFile($absolutePath);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($config) || !isset($config['winzou_state_machine'][$stateMachineName])) {
            return null;
        }

        $winzouConfig = $config['winzou_state_machine'][$stateMachineName];

        /* Conversion de la configuration winzou en Symfony Workflow */
        $workflowConfig = $this->convertToWorkflowConfig($stateMachineName, $winzouConfig);

        /* Generation du fichier YAML de sortie */
        $outputFilePath = $projectPath . '/config/packages/workflow.yaml';
        $existingContent = '';
        if (file_exists($outputFilePath)) {
            $existingContent = (string) file_get_contents($outputFilePath);
        }

        $fixedContent = $this->generateWorkflowYaml($workflowConfig, $existingContent);

        return new MigrationFix(
            confidence: FixConfidence::MEDIUM,
            filePath: $outputFilePath,
            originalContent: $existingContent,
            fixedContent: $fixedContent,
            description: sprintf(
                'Migration de la machine a etats winzou "%s" vers Symfony Workflow. '
                . 'Les etats, transitions et callbacks de base ont ete convertis. '
                . 'Verification manuelle recommandee pour les callbacks complexes.',
                $stateMachineName,
            ),
        );
    }

    /**
     * Convertit une configuration winzou en configuration Symfony Workflow.
     *
     * @param string $name         Nom de la machine a etats
     * @param array<string, mixed> $winzouConfig Configuration winzou
     * @return array<string, mixed> Configuration Symfony Workflow
     */
    private function convertToWorkflowConfig(string $name, array $winzouConfig): array
    {
        $workflow = [
            'type' => 'state_machine',
            'audit_trail' => ['enabled' => true],
            'marking_store' => [
                'type' => 'method',
                'property' => $winzouConfig['property_path'] ?? 'state',
            ],
        ];

        /* Conversion de la classe du sujet en supports */
        if (isset($winzouConfig['class'])) {
            $workflow['supports'] = [$winzouConfig['class']];
        }

        /* Conversion des etats en places */
        if (isset($winzouConfig['states']) && is_array($winzouConfig['states'])) {
            $workflow['places'] = $winzouConfig['states'];
        }

        /* Conversion des transitions */
        if (isset($winzouConfig['transitions']) && is_array($winzouConfig['transitions'])) {
            $workflow['transitions'] = $this->convertTransitions($winzouConfig['transitions']);
        }

        /* Generation des metadonnees pour les callbacks */
        if (isset($winzouConfig['callbacks']) && is_array($winzouConfig['callbacks'])) {
            $workflow['_callbacks_comment'] = $this->convertCallbacksToComments($winzouConfig['callbacks']);
        }

        return [$name => $workflow];
    }

    /**
     * Convertit les transitions winzou au format Symfony Workflow.
     *
     * @param array<string, mixed> $winzouTransitions Transitions winzou
     * @return array<string, array{from: list<string>, to: string}> Transitions Symfony Workflow
     */
    private function convertTransitions(array $winzouTransitions): array
    {
        $transitions = [];

        foreach ($winzouTransitions as $transitionName => $transitionConfig) {
            if (!is_array($transitionConfig)) {
                continue;
            }

            $from = $transitionConfig['from'] ?? [];
            $to = $transitionConfig['to'] ?? null;

            /* winzou utilise "from" comme tableau et "to" comme valeur unique */
            if (is_string($from)) {
                $from = [$from];
            }

            if ($to === null) {
                continue;
            }

            /* Symfony Workflow attend "to" comme valeur unique pour les state machines */
            $transitions[$transitionName] = [
                'from' => $from,
                'to' => is_array($to) ? $to[0] : $to,
            ];
        }

        return $transitions;
    }

    /**
     * Convertit les callbacks winzou en commentaires pour guider la migration manuelle.
     * Les callbacks winzou doivent etre remplaces par des event listeners/subscribers Symfony.
     *
     * @param array<string, mixed> $callbacks Callbacks winzou
     * @return string Instructions de migration des callbacks
     */
    private function convertCallbacksToComments(array $callbacks): string
    {
        $comments = [];

        /* Traitement des callbacks "before" */
        if (isset($callbacks['before']) && is_array($callbacks['before'])) {
            foreach ($callbacks['before'] as $callbackName => $callbackConfig) {
                $comments[] = sprintf(
                    'Callback "before" "%s" : creer un EventSubscriber '
                    . 'ecoutant workflow.%s.transition.* avec priorite positive',
                    $callbackName,
                    'nom_workflow',
                );
            }
        }

        /* Traitement des callbacks "after" */
        if (isset($callbacks['after']) && is_array($callbacks['after'])) {
            foreach ($callbacks['after'] as $callbackName => $callbackConfig) {
                $comments[] = sprintf(
                    'Callback "after" "%s" : creer un EventSubscriber '
                    . 'ecoutant workflow.%s.completed.* avec priorite standard',
                    $callbackName,
                    'nom_workflow',
                );
            }
        }

        /* Traitement des callbacks "guard" */
        if (isset($callbacks['guard']) && is_array($callbacks['guard'])) {
            foreach ($callbacks['guard'] as $callbackName => $callbackConfig) {
                $comments[] = sprintf(
                    'Callback "guard" "%s" : creer un EventSubscriber '
                    . 'ecoutant workflow.%s.guard.* et lancer TransitionBlockedException si necessaire',
                    $callbackName,
                    'nom_workflow',
                );
            }
        }

        return implode("\n", $comments);
    }

    /**
     * Genere le contenu YAML final pour la configuration Symfony Workflow.
     *
     * @param array<string, mixed> $workflowConfig Configuration du workflow
     * @param string               $existingContent Contenu existant du fichier
     * @return string Contenu YAML complet
     */
    private function generateWorkflowYaml(array $workflowConfig, string $existingContent): string
    {
        /* Extraction de la configuration sans les commentaires internes */
        $cleanConfig = $this->cleanCallbackComments($workflowConfig);

        /* Extraction des commentaires de callbacks pour les ajouter en tant que vrais commentaires YAML */
        $callbackComments = $this->extractCallbackComments($workflowConfig);

        $yamlOutput = Yaml::dump(
            ['framework' => ['workflows' => $cleanConfig]],
            8,
            4,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );

        /* Ajout des commentaires de migration des callbacks */
        if ($callbackComments !== '') {
            $yamlOutput .= "\n# --- Migration des callbacks winzou ---\n";
            foreach (explode("\n", $callbackComments) as $comment) {
                if (trim($comment) !== '') {
                    $yamlOutput .= '# ' . $comment . "\n";
                }
            }
        }

        /* Fusion avec le contenu existant */
        if (trim($existingContent) === '') {
            return $yamlOutput;
        }

        return $existingContent . "\n\n# --- Configuration generee automatiquement ---\n" . $yamlOutput;
    }

    /**
     * Supprime les cles de commentaires internes de la configuration.
     *
     * @param array<string, mixed> $config Configuration brute
     * @return array<string, mixed> Configuration nettoyee
     */
    private function cleanCallbackComments(array $config): array
    {
        $cleaned = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $innerCleaned = [];
                foreach ($value as $innerKey => $innerValue) {
                    if ($innerKey === '_callbacks_comment') {
                        continue;
                    }
                    $innerCleaned[$innerKey] = $innerValue;
                }
                $cleaned[$key] = $innerCleaned;
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }

    /**
     * Extrait les commentaires de callbacks de la configuration.
     *
     * @param array<string, mixed> $config Configuration brute
     * @return string Commentaires concatenes
     */
    private function extractCallbackComments(array $config): string
    {
        $comments = [];

        foreach ($config as $value) {
            if (is_array($value) && isset($value['_callbacks_comment'])) {
                $comments[] = $value['_callbacks_comment'];
            }
        }

        return implode("\n", $comments);
    }

    /**
     * Resout le chemin absolu d'un fichier.
     */
    private function resolveAbsolutePath(string $filePath, string $projectPath): string
    {
        if (str_starts_with($filePath, '/') || preg_match('/^[A-Z]:/i', $filePath)) {
            return $filePath;
        }

        return rtrim($projectPath, '/') . '/' . $filePath;
    }
}
