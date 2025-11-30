<?php

use StartupGame\Models\Project;
use StartupGame\Models\Teammate;
use StartupGame\Models\Meeting;
use StartupGame\AI\BotManager;
use StartupGame\Database;

/**
 * Chat Controller - Handles conversations with teammates
 */
class ChatController
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
                case 'send_message':
                    $this->sendMessage();
                    break;

                case 'get_history':
                    $this->getHistory();
                    break;

                case 'meeting_chat':
                    $this->meetingChat();
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
     * Send a message to a teammate (one-on-one chat)
     */
    private function sendMessage(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $teammateId = $_POST['teammate_id'] ?? null;
        $message = $_POST['message'] ?? '';

        if (!$teammateId || empty($message)) {
            throw new Exception('Teammate ID and message are required');
        }

        $teammate = Teammate::find($teammateId);
        if (!$teammate) {
            throw new Exception('Teammate not found');
        }

        // Get API key
        $apiKey = $this->getApiKey($projectId, $teammate->modelProvider);

        // Create bot manager
        $botManager = new BotManager($teammate, $project, $apiKey);

        // Get conversation history
        $history = $botManager->getConversationHistory('one_on_one', $teammateId);

        // Save user message
        $botManager->saveConversation('one_on_one', $teammateId, $message, true);

        // Get bot response
        $response = $botManager->chat($message, $history);

        // Save bot response
        $botManager->saveConversation('one_on_one', $teammateId, $response, false);

        echo json_encode([
            'success' => true,
            'response' => $response,
            'teammate' => $teammate->toArray()
        ]);
    }

    /**
     * Get conversation history
     */
    private function getHistory(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $teammateId = $_GET['teammate_id'] ?? null;
        $conversationType = $_GET['type'] ?? 'one_on_one';

        if (!$teammateId) {
            throw new Exception('Teammate ID is required');
        }

        $sql = "SELECT * FROM conversations
                WHERE project_id = :project_id
                AND conversation_type = :conversation_type
                AND (speaker_id = :teammate_id OR (is_player_message = 1 AND related_id = :teammate_id))
                ORDER BY created_at ASC
                LIMIT 50";

        $history = Database::query($sql, [
            ':project_id' => $projectId,
            ':conversation_type' => $conversationType,
            ':teammate_id' => $teammateId
        ]);

        echo json_encode([
            'success' => true,
            'history' => array_map(fn($msg) => [
                'is_player' => (bool)$msg['is_player_message'],
                'message' => $msg['message'],
                'timestamp' => $msg['created_at']
            ], $history)
        ]);
    }

    /**
     * Handle meeting chat (multi-participant)
     */
    private function meetingChat(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $meetingId = $_POST['meeting_id'] ?? null;
        $message = $_POST['message'] ?? '';

        if (!$meetingId || empty($message)) {
            throw new Exception('Meeting ID and message are required');
        }

        $meeting = Meeting::find($meetingId);
        if (!$meeting) {
            throw new Exception('Meeting not found');
        }

        // Save player message
        $sql = "INSERT INTO conversations (project_id, conversation_type, related_id, speaker_id, is_player_message, message)
                VALUES (:project_id, 'meeting', :meeting_id, NULL, 1, :message)";

        Database::execute($sql, [
            ':project_id' => $projectId,
            ':meeting_id' => $meetingId,
            ':message' => $message
        ]);

        // Determine next speaker using weighted round-robin
        $nextSpeaker = $this->determineNextSpeaker($meeting, $message);

        if ($nextSpeaker) {
            $teammate = Teammate::find($nextSpeaker['teammate_id']);
            $apiKey = $this->getApiKey($projectId, $teammate->modelProvider);

            $botManager = new BotManager($teammate, $project, $apiKey);

            // Get meeting history
            $history = Database::query(
                "SELECT * FROM conversations WHERE project_id = ? AND conversation_type = 'meeting' AND related_id = ? ORDER BY created_at ASC",
                [$projectId, $meetingId]
            );

            // Generate response
            $contextMessage = "In a meeting about: {$meeting->topic}\n\nLatest message: $message";
            $response = $botManager->chat($contextMessage, $history);

            // Save bot response
            $sql = "INSERT INTO conversations (project_id, conversation_type, related_id, speaker_id, is_player_message, message)
                    VALUES (:project_id, 'meeting', :meeting_id, :speaker_id, 0, :message)";

            Database::execute($sql, [
                ':project_id' => $projectId,
                ':meeting_id' => $meetingId,
                ':speaker_id' => $teammate->id,
                ':message' => $response
            ]);

            echo json_encode([
                'success' => true,
                'response' => $response,
                'speaker' => $teammate->toArray()
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'response' => null,
                'message' => 'No one else has anything to add right now.'
            ]);
        }
    }

    /**
     * Determine next speaker in meeting (weighted round-robin)
     */
    private function determineNextSpeaker(Meeting $meeting, string $lastMessage): ?array
    {
        // Simple implementation - randomly pick a participant with some weighting
        // In production, this would be more sophisticated

        $participants = array_filter($meeting->participants, fn($p) => !$p['is_player']);

        if (empty($participants)) {
            return null;
        }

        // Check if message addresses someone directly
        foreach ($participants as $participant) {
            if (stripos($lastMessage, $participant['name']) !== false) {
                return $participant;
            }
        }

        // Random selection (could add more sophisticated weighting)
        return $participants[array_rand($participants)];
    }

    /**
     * Helper methods
     */

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

    private function decryptApiKey(string $encrypted): string
    {
        $encryptionKey = $this->config['encryption_key'];
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $encryptionKey, 0, $iv);
    }
}
