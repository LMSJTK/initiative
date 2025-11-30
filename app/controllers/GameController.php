<?php

use StartupGame\Models\Project;
use StartupGame\Models\Teammate;
use StartupGame\Models\Task;
use StartupGame\Models\Meeting;
use StartupGame\AI\BotManager;
use StartupGame\Database;

/**
 * Game Controller - Handles daily gameplay cycle
 */
class GameController
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
                case 'get_state':
                    $this->getGameState();
                    break;

                case 'start_day':
                    $this->startDay();
                    break;

                case 'start_standup':
                    $this->startStandup();
                    break;

                case 'end_day':
                    $this->endDay();
                    break;

                case 'get_tasks':
                    $this->getTasks();
                    break;

                case 'assign_task':
                    $this->assignTask();
                    break;

                case 'complete_task':
                    $this->completeTask();
                    break;

                case 'get_meetings':
                    $this->getMeetings();
                    break;

                case 'start_meeting':
                    $this->startMeeting();
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
     * Get current game state
     */
    private function getGameState(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $teammates = Teammate::findByProject($projectId);
        $tasks = Task::findByProject($projectId);
        $meetings = Meeting::findByDay($projectId, $project->currentDay);

        echo json_encode([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->projectName,
                'description' => $project->projectDescription,
                'current_day' => $project->currentDay,
                'current_phase' => $project->currentPhase,
                'git_repo_url' => $project->gitRepoUrl
            ],
            'teammates' => array_map(fn($t) => $t->toArray(), $teammates),
            'tasks' => array_map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'assigned_to' => $t->assignedTo
            ], $tasks),
            'meetings' => array_map(fn($m) => [
                'id' => $m->id,
                'type' => $m->meetingType,
                'topic' => $m->topic,
                'status' => $m->status,
                'participants' => $m->participants
            ], $meetings)
        ]);
    }

    /**
     * Start a new day
     */
    private function startDay(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        // Advance day
        $project->advanceDay();

        // Get PM
        $pm = Teammate::findProjectManager($projectId);
        if (!$pm) {
            throw new Exception('Project manager not found');
        }

        // Get API key
        $apiKey = $this->getApiKey($projectId, $pm->modelProvider);
        $botManager = new BotManager($pm, $project, $apiKey);

        // Generate daily overview
        $prompt = "Generate a brief project status overview for Day {$project->currentDay}. " .
            "Include: current progress, key achievements from yesterday, and what's planned for today. " .
            "Keep it concise (3-4 sentences).";

        $overview = $botManager->generate($prompt);

        // Save daily report
        $sql = "INSERT INTO daily_reports (project_id, day_number, phase, pm_overview)
                VALUES (:project_id, :day_number, :phase, :pm_overview)";

        Database::execute($sql, [
            ':project_id' => $projectId,
            ':day_number' => $project->currentDay,
            ':phase' => $project->currentPhase,
            ':pm_overview' => $overview
        ]);

        // Update project phase
        $project->setPhase('standup');

        echo json_encode([
            'success' => true,
            'day' => $project->currentDay,
            'overview' => $overview
        ]);
    }

    /**
     * Start standup meeting
     */
    private function startStandup(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $playerUpdate = $_POST['player_update'] ?? '';

        // Create standup meeting
        $pm = Teammate::findProjectManager($projectId);

        $meeting = new Meeting();
        $meeting->projectId = $projectId;
        $meeting->meetingType = 'standup';
        $meeting->topic = 'Daily Standup';
        $meeting->scheduledDay = $project->currentDay;
        $meeting->status = 'in_progress';
        $meeting->createdBy = $pm->id;
        $meeting->save();

        // Add participants
        $meeting->addParticipant(null, true); // Player
        $meeting->addParticipant($pm->id);

        $teammates = Teammate::findByProject($projectId);
        foreach ($teammates as $teammate) {
            if ($teammate->id !== $pm->id) {
                $meeting->addParticipant($teammate->id);
            }
        }

        // Get updates from each teammate and generate tasks
        $apiKey = $this->getApiKey($projectId, $pm->modelProvider);
        $botManager = new BotManager($pm, $project, $apiKey);

        $standupNotes = "Player update: $playerUpdate\n\n";

        // Collect updates from teammates (simulated)
        foreach ($teammates as $teammate) {
            if ($teammate->id === $pm->id) continue;

            $teammateBot = new BotManager($teammate, $project, $apiKey);
            $update = $teammateBot->generate("Give a brief standup update (1-2 sentences) about what you're working on.");
            $standupNotes .= "{$teammate->name}: $update\n";
        }

        // PM generates tasks based on standup
        $taskPrompt = "Based on the standup updates:\n$standupNotes\n\n" .
            "Generate 2-4 specific tasks for today. For each task, provide: " .
            "title, description, priority (low/medium/high), and recommended teammate role. " .
            "Format as JSON: [{\"title\": \"...\", \"description\": \"...\", \"priority\": \"...\", \"role\": \"...\"}]";

        $taskResponse = $botManager->generate($taskPrompt);
        $taskSuggestions = $this->extractJsonFromResponse($taskResponse);

        // Create tasks
        $tasks = [];
        foreach ($taskSuggestions as $suggestion) {
            $task = new Task();
            $task->projectId = $projectId;
            $task->title = $suggestion['title'] ?? 'Untitled Task';
            $task->description = $suggestion['description'] ?? '';
            $task->priority = $suggestion['priority'] ?? 'medium';
            $task->dayCreated = $project->currentDay;
            $task->recommendedBy = $pm->id;
            $task->status = 'open';

            // Find teammate with matching role
            $recommendedRole = $suggestion['role'] ?? '';
            if ($recommendedRole) {
                $matchingTeammate = array_filter($teammates, fn($t) => $t->role === $recommendedRole);
                if (!empty($matchingTeammate)) {
                    $task->assignedTo = reset($matchingTeammate)->id;
                }
            }

            $task->save();
            $tasks[] = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'assigned_to' => $task->assignedTo
            ];
        }

        // Complete meeting
        $meeting->complete($standupNotes);

        // Update project phase
        $project->setPhase('working');

        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'standup_notes' => $standupNotes
        ]);
    }

    /**
     * End the day
     */
    private function endDay(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        // Get PM to compile day's work
        $pm = Teammate::findProjectManager($projectId);
        $apiKey = $this->getApiKey($projectId, $pm->modelProvider);
        $botManager = new BotManager($pm, $project, $apiKey);

        // Get tasks completed today
        $tasksCompleted = count(Task::findByDay($projectId, $project->currentDay));

        $summary = $botManager->generate(
            "Provide a brief end-of-day summary. We completed $tasksCompleted tasks today. " .
            "Highlight what went well and what's next. Keep it motivational and concise (2-3 sentences)."
        );

        // Update daily report
        $sql = "UPDATE daily_reports SET tasks_completed = :tasks_completed
                WHERE project_id = :project_id AND day_number = :day_number";

        Database::execute($sql, [
            ':tasks_completed' => $tasksCompleted,
            ':project_id' => $projectId,
            ':day_number' => $project->currentDay
        ]);

        // Update project phase
        $project->setPhase('day_end');

        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'tasks_completed' => $tasksCompleted
        ]);
    }

    /**
     * Get tasks
     */
    private function getTasks(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $status = $_GET['status'] ?? null;
        $tasks = Task::findByProject($projectId, $status);

        echo json_encode([
            'success' => true,
            'tasks' => array_map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'assigned_to' => $t->assignedTo
            ], $tasks)
        ]);
    }

    /**
     * Assign task to teammate
     */
    private function assignTask(): void
    {
        $taskId = $_POST['task_id'] ?? null;
        $teammateId = $_POST['teammate_id'] ?? null;

        if (!$taskId || !$teammateId) {
            throw new Exception('Task ID and teammate ID are required');
        }

        $task = Task::find($taskId);
        if (!$task) {
            throw new Exception('Task not found');
        }

        $task->assignTo($teammateId);

        echo json_encode(['success' => true]);
    }

    /**
     * Complete task
     */
    private function completeTask(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        $taskId = $_POST['task_id'] ?? null;

        if (!$taskId) {
            throw new Exception('Task ID is required');
        }

        $project = Project::find($projectId);
        $task = Task::find($taskId);

        if (!$task) {
            throw new Exception('Task not found');
        }

        $task->complete($project->currentDay);

        echo json_encode(['success' => true]);
    }

    /**
     * Get meetings
     */
    private function getMeetings(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        $meetings = Meeting::findByDay($projectId, $project->currentDay);

        echo json_encode([
            'success' => true,
            'meetings' => array_map(fn($m) => [
                'id' => $m->id,
                'type' => $m->meetingType,
                'topic' => $m->topic,
                'status' => $m->status,
                'participants' => $m->participants
            ], $meetings)
        ]);
    }

    /**
     * Start a meeting
     */
    private function startMeeting(): void
    {
        $meetingId = $_POST['meeting_id'] ?? null;

        if (!$meetingId) {
            throw new Exception('Meeting ID is required');
        }

        $meeting = Meeting::find($meetingId);
        if (!$meeting) {
            throw new Exception('Meeting not found');
        }

        $meeting->start();

        echo json_encode([
            'success' => true,
            'meeting' => [
                'id' => $meeting->id,
                'type' => $meeting->meetingType,
                'topic' => $meeting->topic,
                'status' => $meeting->status,
                'participants' => $meeting->participants
            ]
        ]);
    }

    /**
     * Helper methods
     */

    private function extractJsonFromResponse(string $response): array
    {
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

    private function decryptApiKey(string $encrypted): string
    {
        $encryptionKey = $this->config['encryption_key'];
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $encryptionKey, 0, $iv);
    }
}
