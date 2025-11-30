<?php

namespace StartupGame\AI;

use StartupGame\Models\Teammate;
use StartupGame\Models\Project;
use StartupGame\Database;

/**
 * Manages AI bot conversations and context
 */
class BotManager
{
    private Teammate $teammate;
    private Project $project;
    private AIProvider $provider;

    public function __construct(Teammate $teammate, Project $project, string $apiKey)
    {
        $this->teammate = $teammate;
        $this->project = $project;
        $this->provider = AIFactory::createFromTeammate($teammate, $apiKey);
    }

    /**
     * Have the bot respond to a message
     *
     * @param string $userMessage The user's message
     * @param array $conversationHistory Previous messages in the conversation
     * @return string The bot's response
     */
    public function chat(string $userMessage, array $conversationHistory = []): string
    {
        // Build the system prompt based on teammate role and personality
        $systemPrompt = $this->buildSystemPrompt();

        // Get relevant context from knowledge base (RAG)
        $context = $this->getRelevantContext($userMessage);

        // Build messages array
        $messages = [];

        // Add conversation history
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['is_player'] ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        // Add current message with context
        $messageWithContext = $userMessage;
        if (!empty($context)) {
            $messageWithContext = "Context:\n" . implode("\n", $context) . "\n\nMessage: " . $userMessage;
        }

        $messages[] = [
            'role' => 'user',
            'content' => $messageWithContext
        ];

        // Get response from AI
        return $this->provider->chat($messages, [
            'system' => $systemPrompt,
            'temperature' => $this->getTemperature()
        ]);
    }

    /**
     * Generate a standalone response (e.g., for PM daily overview)
     *
     * @param string $prompt The prompt
     * @return string The generated response
     */
    public function generate(string $prompt): string
    {
        $systemPrompt = $this->buildSystemPrompt();
        $context = $this->getRelevantContext($prompt);

        $fullPrompt = $prompt;
        if (!empty($context)) {
            $fullPrompt = "Context:\n" . implode("\n", $context) . "\n\n" . $prompt;
        }

        return $this->provider->generate($fullPrompt, [
            'system' => $systemPrompt,
            'temperature' => $this->getTemperature()
        ]);
    }

    /**
     * Build system prompt based on teammate's role and personality
     */
    private function buildSystemPrompt(): string
    {
        $rolePrompts = [
            'project_manager' => "You are a project manager at a startup called '{$this->project->projectName}'. " .
                "You're responsible for coordinating the team, creating tasks, scheduling meetings, and keeping the project on track. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be professional but friendly. Focus on productivity and clear communication.",

            'frontend_developer' => "You are a frontend developer at a startup called '{$this->project->projectName}'. " .
                "Your specialty is: {$this->teammate->specialty}. " .
                "You're passionate about user experience and writing clean, maintainable code. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be helpful and share your expertise when asked.",

            'backend_developer' => "You are a backend developer at a startup called '{$this->project->projectName}'. " .
                "Your specialty is: {$this->teammate->specialty}. " .
                "You focus on building robust, scalable systems and APIs. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be detail-oriented and thoughtful about architecture.",

            'designer' => "You are a designer at a startup called '{$this->project->projectName}'. " .
                "Your specialty is: {$this->teammate->specialty}. " .
                "You care deeply about aesthetics, usability, and user experience. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be creative and advocate for good design principles.",

            'devops' => "You are a DevOps engineer at a startup called '{$this->project->projectName}'. " .
                "Your specialty is: {$this->teammate->specialty}. " .
                "You focus on infrastructure, deployment, monitoring, and reliability. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be pragmatic and security-conscious.",

            'qa_engineer' => "You are a QA engineer at a startup called '{$this->project->projectName}'. " .
                "Your specialty is: {$this->teammate->specialty}. " .
                "You care about quality, testing, and catching bugs before they reach production. " .
                "The project goal is: {$this->project->projectDescription}. " .
                "Be thorough and detail-oriented."
        ];

        $basePrompt = $rolePrompts[$this->teammate->role] ?? "You are a team member at a startup called '{$this->project->projectName}'. " .
            "Your role is: {$this->teammate->role}. " .
            "The project goal is: {$this->project->projectDescription}.";

        // Add personality traits if available
        if (!empty($this->teammate->personalityTraits)) {
            $traits = is_array($this->teammate->personalityTraits)
                ? implode(', ', $this->teammate->personalityTraits)
                : $this->teammate->personalityTraits;
            $basePrompt .= "\n\nYour personality: " . $traits;
        }

        $basePrompt .= "\n\nYour name is {$this->teammate->name}. Stay in character and be helpful to your teammates.";

        return $basePrompt;
    }

    /**
     * Get relevant context from knowledge base (simple keyword matching for now)
     * In production, this would use vector embeddings for RAG
     */
    private function getRelevantContext(string $query, int $limit = 3): array
    {
        // Simple keyword-based search
        // In production, this would use vector embeddings and similarity search
        $sql = "SELECT title, content FROM knowledge_base
                WHERE project_id = :project_id
                AND (title LIKE :query OR content LIKE :query)
                ORDER BY created_at DESC
                LIMIT :limit";

        $rows = Database::query($sql, [
            ':project_id' => $this->project->id,
            ':query' => '%' . $query . '%',
            ':limit' => $limit
        ]);

        return array_map(function ($row) {
            return "Document: {$row['title']}\n{$row['content']}";
        }, $rows);
    }

    /**
     * Get temperature based on role (some roles need more creativity)
     */
    private function getTemperature(): float
    {
        $temperatures = [
            'project_manager' => 0.7,
            'designer' => 0.8,
            'frontend_developer' => 0.6,
            'backend_developer' => 0.5,
            'devops' => 0.5,
            'qa_engineer' => 0.4
        ];

        return $temperatures[$this->teammate->role] ?? 0.7;
    }

    /**
     * Save conversation to database
     */
    public function saveConversation(string $conversationType, ?int $relatedId, string $message, bool $isPlayer = false): void
    {
        $sql = "INSERT INTO conversations (project_id, conversation_type, related_id, speaker_id, is_player_message, message)
                VALUES (:project_id, :conversation_type, :related_id, :speaker_id, :is_player_message, :message)";

        Database::execute($sql, [
            ':project_id' => $this->project->id,
            ':conversation_type' => $conversationType,
            ':related_id' => $relatedId,
            ':speaker_id' => $isPlayer ? null : $this->teammate->id,
            ':is_player_message' => $isPlayer ? 1 : 0,
            ':message' => $message
        ]);
    }

    /**
     * Get conversation history
     */
    public function getConversationHistory(string $conversationType, ?int $relatedId = null, int $limit = 20): array
    {
        if ($relatedId) {
            $sql = "SELECT * FROM conversations
                    WHERE project_id = :project_id
                    AND conversation_type = :conversation_type
                    AND related_id = :related_id
                    ORDER BY created_at DESC
                    LIMIT :limit";

            $rows = Database::query($sql, [
                ':project_id' => $this->project->id,
                ':conversation_type' => $conversationType,
                ':related_id' => $relatedId,
                ':limit' => $limit
            ]);
        } else {
            $sql = "SELECT * FROM conversations
                    WHERE project_id = :project_id
                    AND conversation_type = :conversation_type
                    ORDER BY created_at DESC
                    LIMIT :limit";

            $rows = Database::query($sql, [
                ':project_id' => $this->project->id,
                ':conversation_type' => $conversationType,
                ':limit' => $limit
            ]);
        }

        return array_reverse($rows); // Return in chronological order
    }
}
