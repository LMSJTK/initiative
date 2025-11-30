<?php

namespace StartupGame\Models;

use StartupGame\Database;

/**
 * Project model - represents a game session
 */
class Project
{
    public ?int $id = null;
    public string $playerName;
    public string $projectName;
    public string $projectDescription;
    public ?string $gitRepoUrl = null;
    public int $currentDay = 0;
    public string $currentPhase = 'setup';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Create a new project
     */
    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        }

        $sql = "INSERT INTO projects (player_name, project_name, project_description, git_repo_url, current_day, current_phase)
                VALUES (:player_name, :project_name, :project_description, :git_repo_url, :current_day, :current_phase)";

        $result = Database::execute($sql, [
            ':player_name' => $this->playerName,
            ':project_name' => $this->projectName,
            ':project_description' => $this->projectDescription,
            ':git_repo_url' => $this->gitRepoUrl,
            ':current_day' => $this->currentDay,
            ':current_phase' => $this->currentPhase
        ]);

        if ($result) {
            $this->id = (int)Database::lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing project
     */
    private function update(): bool
    {
        $sql = "UPDATE projects SET
                player_name = :player_name,
                project_name = :project_name,
                project_description = :project_description,
                git_repo_url = :git_repo_url,
                current_day = :current_day,
                current_phase = :current_phase
                WHERE id = :id";

        return Database::execute($sql, [
            ':id' => $this->id,
            ':player_name' => $this->playerName,
            ':project_name' => $this->projectName,
            ':project_description' => $this->projectDescription,
            ':git_repo_url' => $this->gitRepoUrl,
            ':current_day' => $this->currentDay,
            ':current_phase' => $this->currentPhase
        ]);
    }

    /**
     * Find project by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::queryOne("SELECT * FROM projects WHERE id = ?", [$id]);

        if (!$row) {
            return null;
        }

        return self::fromArray($row);
    }

    /**
     * Get all projects for a player
     */
    public static function findByPlayer(string $playerName): array
    {
        $rows = Database::query("SELECT * FROM projects WHERE player_name = ? ORDER BY created_at DESC", [$playerName]);

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Create instance from database row
     */
    private static function fromArray(array $row): self
    {
        $project = new self();
        $project->id = (int)$row['id'];
        $project->playerName = $row['player_name'];
        $project->projectName = $row['project_name'];
        $project->projectDescription = $row['project_description'];
        $project->gitRepoUrl = $row['git_repo_url'];
        $project->currentDay = (int)$row['current_day'];
        $project->currentPhase = $row['current_phase'];
        $project->createdAt = $row['created_at'];
        $project->updatedAt = $row['updated_at'];

        return $project;
    }

    /**
     * Advance to next day
     */
    public function advanceDay(): bool
    {
        $this->currentDay++;
        return $this->save();
    }

    /**
     * Update project phase
     */
    public function setPhase(string $phase): bool
    {
        $this->currentPhase = $phase;
        return $this->save();
    }

    /**
     * Delete project and all related data
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        return Database::execute("DELETE FROM projects WHERE id = ?", [$this->id]);
    }
}
