<?php

namespace StartupGame\Models;

use StartupGame\Database;

/**
 * Meeting model - represents meetings and conversations
 */
class Meeting
{
    public ?int $id = null;
    public int $projectId;
    public string $meetingType;
    public ?string $topic = null;
    public int $scheduledDay;
    public string $status = 'scheduled';
    public ?int $createdBy = null;
    public ?string $notes = null;
    public ?string $createdAt = null;
    public ?string $completedAt = null;

    // Participants (loaded separately)
    public array $participants = [];

    /**
     * Save meeting
     */
    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        }

        $sql = "INSERT INTO meetings (project_id, meeting_type, topic, scheduled_day, status, created_by, notes)
                VALUES (:project_id, :meeting_type, :topic, :scheduled_day, :status, :created_by, :notes)";

        $result = Database::execute($sql, [
            ':project_id' => $this->projectId,
            ':meeting_type' => $this->meetingType,
            ':topic' => $this->topic,
            ':scheduled_day' => $this->scheduledDay,
            ':status' => $this->status,
            ':created_by' => $this->createdBy,
            ':notes' => $this->notes
        ]);

        if ($result) {
            $this->id = (int)Database::lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing meeting
     */
    private function update(): bool
    {
        $sql = "UPDATE meetings SET
                meeting_type = :meeting_type,
                topic = :topic,
                scheduled_day = :scheduled_day,
                status = :status,
                notes = :notes
                WHERE id = :id";

        return Database::execute($sql, [
            ':id' => $this->id,
            ':meeting_type' => $this->meetingType,
            ':topic' => $this->topic,
            ':scheduled_day' => $this->scheduledDay,
            ':status' => $this->status,
            ':notes' => $this->notes
        ]);
    }

    /**
     * Add participant to meeting
     */
    public function addParticipant(?int $teammateId = null, bool $isPlayer = false): bool
    {
        if (!$this->id) {
            return false;
        }

        $sql = "INSERT INTO meeting_participants (meeting_id, teammate_id, is_player)
                VALUES (:meeting_id, :teammate_id, :is_player)
                ON DUPLICATE KEY UPDATE teammate_id = teammate_id";

        return Database::execute($sql, [
            ':meeting_id' => $this->id,
            ':teammate_id' => $teammateId,
            ':is_player' => $isPlayer ? 1 : 0
        ]);
    }

    /**
     * Load participants
     */
    public function loadParticipants(): void
    {
        if (!$this->id) {
            return;
        }

        $rows = Database::query(
            "SELECT mp.*, t.name, t.role, t.color
             FROM meeting_participants mp
             LEFT JOIN teammates t ON mp.teammate_id = t.id
             WHERE mp.meeting_id = ?",
            [$this->id]
        );

        $this->participants = array_map(function ($row) {
            return [
                'teammate_id' => $row['teammate_id'] ? (int)$row['teammate_id'] : null,
                'is_player' => (bool)$row['is_player'],
                'name' => $row['is_player'] ? 'You' : ($row['name'] ?? 'Unknown'),
                'role' => $row['role'] ?? '',
                'color' => $row['color'] ?? '#999999'
            ];
        }, $rows);
    }

    /**
     * Find meeting by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::queryOne("SELECT * FROM meetings WHERE id = ?", [$id]);

        if (!$row) {
            return null;
        }

        $meeting = self::fromArray($row);
        $meeting->loadParticipants();

        return $meeting;
    }

    /**
     * Get meetings for a specific day
     */
    public static function findByDay(int $projectId, int $day, ?string $status = null): array
    {
        if ($status) {
            $rows = Database::query(
                "SELECT * FROM meetings WHERE project_id = ? AND scheduled_day = ? AND status = ? ORDER BY created_at",
                [$projectId, $day, $status]
            );
        } else {
            $rows = Database::query(
                "SELECT * FROM meetings WHERE project_id = ? AND scheduled_day = ? ORDER BY created_at",
                [$projectId, $day]
            );
        }

        return array_map(function ($row) {
            $meeting = self::fromArray($row);
            $meeting->loadParticipants();
            return $meeting;
        }, $rows);
    }

    /**
     * Get all meetings for a project
     */
    public static function findByProject(int $projectId): array
    {
        $rows = Database::query(
            "SELECT * FROM meetings WHERE project_id = ? ORDER BY scheduled_day DESC, created_at DESC",
            [$projectId]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Create instance from database row
     */
    private static function fromArray(array $row): self
    {
        $meeting = new self();
        $meeting->id = (int)$row['id'];
        $meeting->projectId = (int)$row['project_id'];
        $meeting->meetingType = $row['meeting_type'];
        $meeting->topic = $row['topic'];
        $meeting->scheduledDay = (int)$row['scheduled_day'];
        $meeting->status = $row['status'];
        $meeting->createdBy = $row['created_by'] ? (int)$row['created_by'] : null;
        $meeting->notes = $row['notes'];
        $meeting->createdAt = $row['created_at'];
        $meeting->completedAt = $row['completed_at'];

        return $meeting;
    }

    /**
     * Mark meeting as completed
     */
    public function complete(string $notes = ''): bool
    {
        $this->status = 'completed';
        $this->notes = $notes;

        $sql = "UPDATE meetings SET status = 'completed', notes = :notes, completed_at = NOW()
                WHERE id = :id";

        return Database::execute($sql, [
            ':id' => $this->id,
            ':notes' => $notes
        ]);
    }

    /**
     * Mark meeting as in progress
     */
    public function start(): bool
    {
        $this->status = 'in_progress';
        return $this->save();
    }
}
