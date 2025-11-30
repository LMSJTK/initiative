<?php

use StartupGame\Models\Project;
use StartupGame\Models\Teammate;
use StartupGame\Database;

/**
 * Settings Controller - Manages API keys and configuration
 */
class SettingsController
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
                case 'save_api_key':
                    $this->saveApiKey();
                    break;

                case 'get_settings':
                    $this->getSettings();
                    break;

                case 'update_teammate_model':
                    $this->updateTeammateModel();
                    break;

                case 'test_api_key':
                    $this->testApiKey();
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

        // Validate provider
        $validProviders = ['anthropic', 'openai', 'google'];
        if (!in_array($provider, $validProviders)) {
            throw new Exception('Invalid provider');
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
     * Get current settings
     */
    private function getSettings(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        // Get configured API keys (without exposing the actual keys)
        $apiKeys = Database::query(
            "SELECT provider FROM api_keys WHERE project_id = ?",
            [$projectId]
        );

        $configuredProviders = array_column($apiKeys, 'provider');

        // Get teammates and their models
        $teammates = Teammate::findByProject($projectId);

        echo json_encode([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->projectName,
                'description' => $project->projectDescription,
                'git_repo_url' => $project->gitRepoUrl
            ],
            'configured_providers' => $configuredProviders,
            'teammates' => array_map(fn($t) => $t->toArray(), $teammates),
            'available_models' => $this->getAvailableModels()
        ]);
    }

    /**
     * Update teammate's AI model
     */
    private function updateTeammateModel(): void
    {
        $teammateId = $_POST['teammate_id'] ?? null;
        $model = $_POST['model'] ?? '';

        if (!$teammateId || empty($model)) {
            throw new Exception('Teammate ID and model are required');
        }

        $teammate = Teammate::find($teammateId);
        if (!$teammate) {
            throw new Exception('Teammate not found');
        }

        // Parse model string
        list($provider, $version) = $this->parseModelString($model);

        $teammate->modelProvider = $provider;
        $teammate->modelVersion = $version;
        $teammate->save();

        echo json_encode(['success' => true]);
    }

    /**
     * Test API key
     */
    private function testApiKey(): void
    {
        $provider = $_POST['provider'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';

        if (empty($provider) || empty($apiKey)) {
            throw new Exception('Provider and API key are required');
        }

        // Try to make a simple API call
        try {
            $factory = new \StartupGame\AI\AIFactory();
            $ai = $factory::create($provider, $apiKey, $this->getDefaultModel($provider));

            $response = $ai->generate('Say "API key working" in one word.');

            echo json_encode([
                'success' => true,
                'message' => 'API key is valid',
                'test_response' => $response
            ]);
        } catch (Exception $e) {
            throw new Exception('API key test failed: ' . $e->getMessage());
        }
    }

    /**
     * Helper methods
     */

    private function encryptApiKey(string $key): string
    {
        $encryptionKey = $this->config['encryption_key'];
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($key, 'AES-256-CBC', $encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
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

    private function getDefaultModel(string $provider): string
    {
        $defaults = [
            'anthropic' => 'claude-sonnet-4.5',
            'openai' => 'gpt-5.1',
            'google' => 'gemini-3.0'
        ];

        return $defaults[$provider] ?? 'claude-sonnet-4.5';
    }

    private function getAvailableModels(): array
    {
        return [
            'anthropic' => [
                ['id' => 'claude-sonnet-4.5', 'name' => 'Claude Sonnet 4.5'],
                ['id' => 'claude-opus-4.5', 'name' => 'Claude Opus 4.5']
            ],
            'openai' => [
                ['id' => 'gpt-5.1', 'name' => 'ChatGPT 5.1']
            ],
            'google' => [
                ['id' => 'gemini-3.0', 'name' => 'Gemini 3.0']
            ]
        ];
    }
}
