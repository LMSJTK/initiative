<?php

use StartupGame\Models\Project;
use StartupGame\Models\Teammate;
use StartupGame\Models\Meeting;
use StartupGame\AI\BotManager;
use StartupGame\AI\AIFactory;
use StartupGame\Database;

/**
 * Setup Controller - Handles initial project setup
 */
class SetupController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'create_project':
                    $this->createProject();
                    break;

                case 'chat_with_pm':
                    $this->chatWithPM();
                    break;

                case 'finalize_setup':
                    $this->finalizeSetup();
                    break;

                case 'save_api_key':
                    $this->saveApiKey();
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Create initial project
     */
    private function createProject(): void
    {
        $playerName = $_POST['player_name'] ?? 'Player';
        $projectIdea = $_POST['project_idea'] ?? '';

        if (empty($projectIdea)) {
            throw new Exception('Project idea is required');
        }

        // Create project
        $project = new Project();
        $project->playerName = $playerName;
        $project->projectName = $this->extractProjectName($projectIdea);
        $project->projectDescription = $projectIdea;
        $project->currentPhase = 'setup';
        $project->save();

        // Store in session
        $_SESSION['project_id'] = $project->id;
        $_SESSION['player_name'] = $playerName;

        echo json_encode([
            'success' => true,
            'project_id' => $project->id,
            'message' => 'Project created successfully'
        ]);
    }

    /**
     * Chat with PM during setup
     */
    private function chatWithPM(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $message = $_POST['message'] ?? '';
        if (empty($message)) {
            throw new Exception('Message is required');
        }

        // Get or create PM
        $pm = Teammate::findProjectManager($projectId);
        if (!$pm) {
            $pm = $this->createDefaultPM($project);
        }

        // Get API key
        $apiKey = $this->getApiKey($projectId, $pm->modelProvider);

        // Create bot manager and get response
        $botManager = new BotManager($pm, $project, $apiKey);

        // Get conversation history
        $history = $botManager->getConversationHistory('setup', $project->id);

        // Save user message
        $botManager->saveConversation('setup', $project->id, $message, true);

        // Get bot response
        $response = $botManager->chat($message, $history);

        // Save bot response
        $botManager->saveConversation('setup', $project->id, $response, false);

        echo json_encode([
            'success' => true,
            'response' => $response,
            'pm' => $pm->toArray()
        ]);
    }

    /**
     * Finalize setup and generate team
     */
    private function finalizeSetup(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $pmModel = $_POST['pm_model'] ?? 'claude-sonnet-4.5';
        $assistantModel = $_POST['assistant_model'] ?? 'claude-sonnet-4.5';

        // Create PM if not exists
        $pm = Teammate::findProjectManager($projectId);
        if (!$pm) {
            $pm = $this->createDefaultPM($project);
        }

        // Update PM model
        list($provider, $version) = $this->parseModelString($pmModel);
        $pm->modelProvider = $provider;
        $pm->modelVersion = $version;
        $pm->save();

        // Get API key and generate team
        $apiKey = $this->getApiKey($projectId, $pm->modelProvider);
        $botManager = new BotManager($pm, $project, $apiKey);

        // Ask PM to generate team
        $prompt = "Based on our conversation about building '{$project->projectDescription}', " .
            "generate a list of 3-5 teammates we need for this project. " .
            "For each teammate, provide: role, specialty, and a brief personality description. " .
            "Format as JSON: [{\"role\": \"...\", \"specialty\": \"...\", \"personality\": \"...\"}]";

        $response = $botManager->generate($prompt);

        // Parse team suggestions (extract JSON from response)
        $teamSuggestions = $this->extractJsonFromResponse($response);

        if (empty($teamSuggestions)) {
            throw new Exception('Failed to generate team');
        }

        // Create teammates
        $teammates = [];
        $colors = $this->config['game']['available_colors'];
        $colorIndex = 0;

        foreach ($teamSuggestions as $suggestion) {
            $teammate = new Teammate();
            $teammate->projectId = $project->id;
            $teammate->name = $this->generateName($suggestion['role']);
            $teammate->role = $this->normalizeRole($suggestion['role']);
            $teammate->specialty = $suggestion['specialty'] ?? '';
            $teammate->color = $colors[$colorIndex % count($colors)];
            $teammate->personalityTraits = [$suggestion['personality'] ?? 'Helpful and professional'];

            // Assign default model (user can change later)
            list($provider, $version) = $this->parseModelString($assistantModel);
            $teammate->modelProvider = $provider;
            $teammate->modelVersion = $version;

            // Assign desk position (in a row)
            $teammate->deskPositionX = 200 + ($colorIndex * 120);
            $teammate->deskPositionY = 300;

            $teammate->save();
            $teammates[] = $teammate->toArray();

            $colorIndex++;
        }

        // Update project phase
        $project->setPhase('day_start');

        echo json_encode([
            'success' => true,
            'teammates' => $teammates,
            'message' => 'Team generated successfully!'
        ]);
    }

    /**
     * Save API key for a provider
     */
    private function saveApiKey(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $provider = $_POST['provider'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';

        if (empty($provider) || empty($apiKey)) {
            throw new Exception('Provider and API key are required');
        }

        // Encrypt API key
        $encryptedKey = $this->encryptApiKey($apiKey);

        // Save to database
        $sql = "INSERT INTO api_keys (project_id, provider, encrypted_key)
                VALUES (:project_id, :provider, :encrypted_key)
                ON DUPLICATE KEY UPDATE encrypted_key = :encrypted_key";

        Database::execute($sql, [
            ':project_id' => $projectId,
            ':provider' => $provider,
            ':encrypted_key' => $encryptedKey
        ]);

        echo json_encode(['success' => true]);
    }

    /**
     * Helper methods
     */

    private function extractProjectName(string $idea): string
    {
        // Simple extraction - take first few words
        $words = explode(' ', $idea);
        $name = implode(' ', array_slice($words, 0, min(3, count($words))));
        return ucwords($name);
    }

    private function createDefaultPM(Project $project): Teammate
    {
        $pm = new Teammate();
        $pm->projectId = $project->id;
        $pm->name = 'Alex';
        $pm->role = 'project_manager';
        $pm->specialty = 'Agile project management';
        $pm->modelProvider = 'anthropic';
        $pm->modelVersion = 'claude-sonnet-4.5';
        $pm->color = '#2c3e50';
        $pm->deskPositionX = 100;
        $pm->deskPositionY = 100;
        $pm->personalityTraits = ['Organized', 'Strategic', 'Collaborative'];
        $pm->save();

        return $pm;
    }

    private function parseModelString(string $model): array
    {
        $modelMap = [
            'claude-sonnet-4.5' => ['anthropic', 'claude-sonnet-4.5'],
            'claude-opus-4.5' => ['anthropic', 'claude-opus-4.5'],
            'chatgpt-5.1' => ['openai', 'gpt-5.1'],
            'gpt-5.1' => ['openai', 'gpt-5.1'],
            'gemini-3.0' => ['google', 'gemini-3.0']
        ];

        return $modelMap[$model] ?? ['anthropic', 'claude-sonnet-4.5'];
    }

    private function normalizeRole(string $role): string
    {
        $roleMap = [
            'frontend' => 'frontend_developer',
            'backend' => 'backend_developer',
            'design' => 'designer',
            'qa' => 'qa_engineer',
            'devops' => 'devops'
        ];

        $normalized = strtolower(str_replace([' ', '-'], '_', $role));

        return $roleMap[$normalized] ?? $normalized;
    }

    private function generateName(string $role): string
    {
        $names = [
            'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley',
            'Avery', 'Quinn', 'Jamie', 'Drew', 'Sage'
        ];

        return $names[array_rand($names)];
    }

    private function extractJsonFromResponse(string $response): array
    {
        // Try to find JSON in the response
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return [];
    }

    private function getApiKey(int $projectId, string $provider): string
    {
        $row = Database::queryOne(
            "SELECT encrypted_key FROM api_keys WHERE project_id = ? AND provider = ?",
            [$projectId, $provider]
        );

        if (!$row) {
            throw new Exception("API key not found for provider: $provider");
        }

        return $this->decryptApiKey($row['encrypted_key']);
    }

    private function encryptApiKey(string $key): string
    {
        $encryptionKey = $this->config['encryption_key'];
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($key, 'AES-256-CBC', $encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decryptApiKey(string $encrypted): string
    {
        $encryptionKey = $this->config['encryption_key'];
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $encryptionKey, 0, $iv);
    }
}
