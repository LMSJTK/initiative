<?php

namespace StartupGame\Models;

use StartupGame\Database;

/**
 * Task model - represents a work item/ticket
 */
class Task
{
    public ?int $id = null;
    public int $projectId;
    public string $title;
    public ?string $description = null;
    public ?int $assignedTo = null;
    public ?int $recommendedBy = null;
    public string $status = 'open';
    public string $priority = 'medium';
    public int $dayCreated;
    public ?int $dayCompleted = null;
    public ?string $gitBranch = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Save task
     */
    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        }

        $sql = "INSERT INTO tasks (project_id, title, description, assigned_to, recommended_by,
                status, priority, day_created, day_completed, git_branch)
                VALUES (:project_id, :title, :description, :assigned_to, :recommended_by,
                :status, :priority, :day_created, :day_completed, :git_branch)";

        $result = Database::execute($sql, [
            ':project_id' => $this->projectId,
            ':title' => $this->title,
            ':description' => $this->description,
            ':assigned_to' => $this->assignedTo,
            ':recommended_by' => $this->recommendedBy,
            ':status' => $this->status,
            ':priority' => $this->priority,
            ':day_created' => $this->dayCreated,
            ':day_completed' => $this->dayCompleted,
            ':git_branch' => $this->gitBranch
        ]);

        if ($result) {
            $this->id = (int)Database::lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing task
     */
    private function update(): bool
    {
        $sql = "UPDATE tasks SET
                title = :title,
                description = :description,
                assigned_to = :assigned_to,
                recommended_by = :recommended_by,
                status = :status,
                priority = :priority,
                day_completed = :day_completed,
                git_branch = :git_branch
                WHERE id = :id";

        return Database::execute($sql, [
            ':id' => $this->id,
            ':title' => $this->title,
            ':description' => $this->description,
            ':assigned_to' => $this->assignedTo,
            ':recommended_by' => $this->recommendedBy,
            ':status' => $this->status,
            ':priority' => $this->priority,
            ':day_completed' => $this->dayCompleted,
            ':git_branch' => $this->gitBranch
        ]);
    }

    /**
     * Find task by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::queryOne("SELECT * FROM tasks WHERE id = ?", [$id]);

        if (!$row) {
            return null;
        }

        return self::fromArray($row);
    }

    /**
     * Get all tasks for a project
     */
    public static function findByProject(int $projectId, ?string $status = null): array
    {
        if ($status) {
            $rows = Database::query(
                "SELECT * FROM tasks WHERE project_id = ? AND status = ? ORDER BY priority DESC, created_at DESC",
                [$projectId, $status]
            );
        } else {
            $rows = Database::query(
                "SELECT * FROM tasks WHERE project_id = ? ORDER BY status, priority DESC, created_at DESC",
                [$projectId]
            );
        }

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Get tasks for current day
     */
    public static function findByDay(int $projectId, int $day): array
    {
        $rows = Database::query(
            "SELECT * FROM tasks WHERE project_id = ? AND day_created = ? ORDER BY priority DESC",
            [$projectId, $day]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Get tasks assigned to a teammate
     */
    public static function findByTeammate(int $teammateId, ?string $status = null): array
    {
        if ($status) {
            $rows = Database::query(
                "SELECT * FROM tasks WHERE assigned_to = ? AND status = ? ORDER BY priority DESC",
                [$teammateId, $status]
            );
        } else {
            $rows = Database::query(
                "SELECT * FROM tasks WHERE assigned_to = ? ORDER BY status, priority DESC",
                [$teammateId]
            );
        }

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Create instance from database row
     */
    private static function fromArray(array $row): self
    {
        $task = new self();
        $task->id = (int)$row['id'];
        $task->projectId = (int)$row['project_id'];
        $task->title = $row['title'];
        $task->description = $row['description'];
        $task->assignedTo = $row['assigned_to'] ? (int)$row['assigned_to'] : null;
        $task->recommendedBy = $row['recommended_by'] ? (int)$row['recommended_by'] : null;
        $task->status = $row['status'];
        $task->priority = $row['priority'];
        $task->dayCreated = (int)$row['day_created'];
        $task->dayCompleted = $row['day_completed'] ? (int)$row['day_completed'] : null;
        $task->gitBranch = $row['git_branch'];
        $task->createdAt = $row['created_at'];
        $task->updatedAt = $row['updated_at'];

        return $task;
    }

    /**
     * Mark task as completed
     */
    public function complete(int $day): bool
    {
        $this->status = 'completed';
        $this->dayCompleted = $day;
        return $this->save();
    }

    /**
     * Assign task to teammate
     */
    public function assignTo(int $teammateId): bool
    {
        $this->assignedTo = $teammateId;
        $this->status = 'in_progress';
        return $this->save();
    }
}
