<?php

namespace StartupGame\Models;

use StartupGame\Database;

/**
 * Teammate model - represents an AI bot teammate
 */
class Teammate
{
    public ?int $id = null;
    public int $projectId;
    public string $name;
    public string $role;
    public ?string $specialty = null;
    public string $modelProvider;
    public string $modelVersion;
    public string $color = '#3498db';
    public int $deskPositionX = 0;
    public int $deskPositionY = 0;
    public ?array $personalityTraits = null;
    public ?string $createdAt = null;

    /**
     * Save teammate
     */
    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        }

        $sql = "INSERT INTO teammates (project_id, name, role, specialty, model_provider, model_version,
                color, desk_position_x, desk_position_y, personality_traits)
                VALUES (:project_id, :name, :role, :specialty, :model_provider, :model_version,
                :color, :desk_position_x, :desk_position_y, :personality_traits)";

        $result = Database::execute($sql, [
            ':project_id' => $this->projectId,
            ':name' => $this->name,
            ':role' => $this->role,
            ':specialty' => $this->specialty,
            ':model_provider' => $this->modelProvider,
            ':model_version' => $this->modelVersion,
            ':color' => $this->color,
            ':desk_position_x' => $this->deskPositionX,
            ':desk_position_y' => $this->deskPositionY,
            ':personality_traits' => $this->personalityTraits ? json_encode($this->personalityTraits) : null
        ]);

        if ($result) {
            $this->id = (int)Database::lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing teammate
     */
    private function update(): bool
    {
        $sql = "UPDATE teammates SET
                name = :name,
                role = :role,
                specialty = :specialty,
                model_provider = :model_provider,
                model_version = :model_version,
                color = :color,
                desk_position_x = :desk_position_x,
                desk_position_y = :desk_position_y,
                personality_traits = :personality_traits
                WHERE id = :id";

        return Database::execute($sql, [
            ':id' => $this->id,
            ':name' => $this->name,
            ':role' => $this->role,
            ':specialty' => $this->specialty,
            ':model_provider' => $this->modelProvider,
            ':model_version' => $this->modelVersion,
            ':color' => $this->color,
            ':desk_position_x' => $this->deskPositionX,
            ':desk_position_y' => $this->deskPositionY,
            ':personality_traits' => $this->personalityTraits ? json_encode($this->personalityTraits) : null
        ]);
    }

    /**
     * Find teammate by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::queryOne("SELECT * FROM teammates WHERE id = ?", [$id]);

        if (!$row) {
            return null;
        }

        return self::fromArray($row);
    }

    /**
     * Get all teammates for a project
     */
    public static function findByProject(int $projectId): array
    {
        $rows = Database::query("SELECT * FROM teammates WHERE project_id = ? ORDER BY role, name", [$projectId]);

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Find project manager for a project
     */
    public static function findProjectManager(int $projectId): ?self
    {
        $row = Database::queryOne(
            "SELECT * FROM teammates WHERE project_id = ? AND role = 'project_manager' LIMIT 1",
            [$projectId]
        );

        if (!$row) {
            return null;
        }

        return self::fromArray($row);
    }

    /**
     * Find teammates by role
     */
    public static function findByRole(int $projectId, string $role): array
    {
        $rows = Database::query(
            "SELECT * FROM teammates WHERE project_id = ? AND role = ? ORDER BY name",
            [$projectId, $role]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Create instance from database row
     */
    private static function fromArray(array $row): self
    {
        $teammate = new self();
        $teammate->id = (int)$row['id'];
        $teammate->projectId = (int)$row['project_id'];
        $teammate->name = $row['name'];
        $teammate->role = $row['role'];
        $teammate->specialty = $row['specialty'];
        $teammate->modelProvider = $row['model_provider'];
        $teammate->modelVersion = $row['model_version'];
        $teammate->color = $row['color'];
        $teammate->deskPositionX = (int)$row['desk_position_x'];
        $teammate->deskPositionY = (int)$row['desk_position_y'];
        $teammate->personalityTraits = $row['personality_traits'] ? json_decode($row['personality_traits'], true) : null;
        $teammate->createdAt = $row['created_at'];

        return $teammate;
    }

    /**
     * Get display info for UI
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'specialty' => $this->specialty,
            'model' => $this->modelProvider . ' ' . $this->modelVersion,
            'color' => $this->color,
            'position' => [
                'x' => $this->deskPositionX,
                'y' => $this->deskPositionY
            ],
            'traits' => $this->personalityTraits
        ];
    }
}
