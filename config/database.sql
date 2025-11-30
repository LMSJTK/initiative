-- Startup Game Database Schema
-- This database stores game state, bot contexts, and RAG data
-- NOT for the application being built within the game

CREATE DATABASE IF NOT EXISTS startup_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE startup_game;

-- Game sessions/projects
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_name VARCHAR(255) NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT,
    git_repo_url VARCHAR(512),
    current_day INT DEFAULT 0,
    current_phase VARCHAR(100) DEFAULT 'setup',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_player (player_name),
    INDEX idx_phase (current_phase)
) ENGINE=InnoDB;

-- AI teammates/bots
CREATE TABLE IF NOT EXISTS teammates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL,
    specialty TEXT,
    model_provider VARCHAR(50) NOT NULL, -- 'gemini', 'chatgpt', 'claude'
    model_version VARCHAR(50) NOT NULL, -- '3.0', '5.1', 'sonnet-4.5', 'opus-4.5'
    color VARCHAR(7) DEFAULT '#3498db', -- hex color for circle avatar
    desk_position_x INT DEFAULT 0,
    desk_position_y INT DEFAULT 0,
    personality_traits TEXT, -- JSON array of personality characteristics
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Player's coding assistant
CREATE TABLE IF NOT EXISTS player_assistants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    model_provider VARCHAR(50) NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_assistant (project_id)
) ENGINE=InnoDB;

-- Daily progress and reports
CREATE TABLE IF NOT EXISTS daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    day_number INT NOT NULL,
    phase VARCHAR(100),
    pm_overview TEXT, -- AI-generated overview from PM
    tasks_completed INT DEFAULT 0,
    tasks_created INT DEFAULT 0,
    commits_made INT DEFAULT 0,
    report_data JSON, -- Stores kanban boards, gantt charts, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_day (project_id, day_number),
    INDEX idx_project_day (project_id, day_number)
) ENGINE=InnoDB;

-- Tasks/tickets
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT, -- teammate_id
    recommended_by INT, -- PM teammate_id who recommended
    status VARCHAR(50) DEFAULT 'open', -- 'open', 'in_progress', 'review', 'completed'
    priority VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    day_created INT NOT NULL,
    day_completed INT,
    git_branch VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES teammates(id) ON DELETE SET NULL,
    INDEX idx_project_status (project_id, status),
    INDEX idx_assigned (assigned_to)
) ENGINE=InnoDB;

-- Meetings/calendar events
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    meeting_type VARCHAR(50) NOT NULL, -- 'standup', 'one_on_one', 'whiteboard', 'scheduled'
    topic VARCHAR(255),
    scheduled_day INT NOT NULL,
    status VARCHAR(50) DEFAULT 'scheduled', -- 'scheduled', 'in_progress', 'completed'
    created_by INT, -- teammate_id (usually PM)
    notes TEXT, -- Meeting notes/transcript
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES teammates(id) ON DELETE SET NULL,
    INDEX idx_project_day (project_id, scheduled_day),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Meeting participants
CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    teammate_id INT,
    is_player BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (teammate_id) REFERENCES teammates(id) ON DELETE CASCADE,
    UNIQUE KEY unique_meeting_participant (meeting_id, teammate_id)
) ENGINE=InnoDB;

-- Conversation history (for RAG and context)
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    conversation_type VARCHAR(50) NOT NULL, -- 'meeting', 'one_on_one', 'coding_session'
    related_id INT, -- meeting_id or task_id
    speaker_id INT, -- teammate_id or NULL for player
    is_player_message BOOLEAN DEFAULT FALSE,
    message TEXT NOT NULL,
    message_embedding BLOB, -- Store vector embeddings for RAG
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (speaker_id) REFERENCES teammates(id) ON DELETE SET NULL,
    INDEX idx_project_type (project_id, conversation_type),
    INDEX idx_related (related_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Git commits tracking
CREATE TABLE IF NOT EXISTS git_commits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT,
    branch_name VARCHAR(255) NOT NULL,
    commit_hash VARCHAR(40),
    commit_message TEXT,
    author_id INT, -- teammate_id
    is_merged BOOLEAN DEFAULT FALSE,
    merge_approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES teammates(id) ON DELETE SET NULL,
    INDEX idx_project_branch (project_id, branch_name),
    INDEX idx_task (task_id)
) ENGINE=InnoDB;

-- Bot knowledge base / RAG documents
CREATE TABLE IF NOT EXISTS knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL, -- 'spec', 'architecture', 'decision', 'note'
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_embedding BLOB, -- Vector embeddings for RAG
    created_by INT, -- teammate_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES teammates(id) ON DELETE SET NULL,
    INDEX idx_project_type (project_id, document_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- API keys configuration (encrypted)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL, -- 'openai', 'anthropic', 'google'
    encrypted_key TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_provider (project_id, provider)
) ENGINE=InnoDB;

-- Player activity log
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    day_number INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- 'coding', 'meeting', 'chat', 'whiteboard'
    activity_description TEXT,
    duration_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_day (project_id, day_number)
) ENGINE=InnoDB;
