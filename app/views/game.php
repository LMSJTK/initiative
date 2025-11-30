<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project->projectName) ?> - Startup Game</title>
    <link rel="stylesheet" href="<?= $this->config['app']['base_url'] ?>/assets/css/style.css">
</head>
<body class="game-page">
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <h1><?= htmlspecialchars($project->projectName) ?></h1>
            <span class="day-counter">Day <span id="currentDay"><?= $project->currentDay ?></span></span>
            <span class="phase-indicator" id="currentPhase"><?= ucfirst(str_replace('_', ' ', $project->currentPhase)) ?></span>
        </div>

        <div class="nav-right">
            <?php if ($project->gitRepoUrl): ?>
                <a href="file://<?= htmlspecialchars($project->gitRepoUrl) ?>" target="_blank" class="btn-git">
                    Open Git Repo
                </a>
            <?php endif; ?>
            <button id="btnSettings" class="btn-icon">⚙️</button>
        </div>
    </nav>

    <!-- Main Game Area -->
    <div class="game-container">
        <!-- Office View (Top-down) -->
        <div id="officeView" class="office-view">
            <svg id="officeSvg" width="1200" height="800">
                <!-- Conference room -->
                <rect x="800" y="100" width="300" height="200" class="conference-room" />
                <text x="950" y="190" text-anchor="middle" class="room-label">Conference Room</text>

                <!-- Player desk -->
                <rect x="100" y="100" width="80" height="60" class="desk player-desk" data-desk="player" />
                <text x="140" y="135" text-anchor="middle" class="desk-label">You</text>

                <!-- Teammate desks will be added dynamically -->
            </svg>

            <!-- Action buttons overlay -->
            <div class="action-panel">
                <button id="btnStartDay" class="btn-action">Start Day</button>
                <button id="btnStandup" class="btn-action" disabled>Start Standup</button>
                <button id="btnEndDay" class="btn-action" disabled>End Day</button>
            </div>
        </div>

        <!-- Interaction Overlay (Chat/Terminal) -->
        <div id="interactionOverlay" class="interaction-overlay hidden">
            <div class="overlay-header">
                <h2 id="overlayTitle"></h2>
                <button id="btnCloseOverlay" class="btn-close">×</button>
            </div>

            <div id="overlayContent" class="overlay-content">
                <!-- Content will be dynamically loaded -->
            </div>
        </div>

        <!-- Side Panel (Tasks/Meetings) -->
        <aside class="side-panel">
            <div class="panel-tabs">
                <button class="tab-btn active" data-tab="tasks">Tasks</button>
                <button class="tab-btn" data-tab="meetings">Meetings</button>
                <button class="tab-btn" data-tab="commits">Commits</button>
            </div>

            <div class="panel-content">
                <!-- Tasks Tab -->
                <div id="tasksPanel" class="tab-panel active">
                    <div id="tasksList" class="tasks-list">
                        <p class="empty-state">No tasks yet. Start your day to get tasks!</p>
                    </div>
                </div>

                <!-- Meetings Tab -->
                <div id="meetingsPanel" class="tab-panel">
                    <div id="meetingsList" class="meetings-list">
                        <p class="empty-state">No meetings scheduled.</p>
                    </div>
                </div>

                <!-- Commits Tab -->
                <div id="commitsPanel" class="tab-panel">
                    <div id="commitsList" class="commits-list">
                        <p class="empty-state">No commits yet.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Settings</h2>
                <button class="btn-close" onclick="closeSettings()">×</button>
            </div>

            <div class="modal-body">
                <h3>API Keys</h3>
                <div class="form-group">
                    <label>Anthropic API Key</label>
                    <input type="password" id="settingsAnthropicKey" placeholder="sk-ant-...">
                    <button class="btn-save" data-provider="anthropic">Save</button>
                </div>

                <div class="form-group">
                    <label>OpenAI API Key</label>
                    <input type="password" id="settingsOpenAIKey" placeholder="sk-...">
                    <button class="btn-save" data-provider="openai">Save</button>
                </div>

                <div class="form-group">
                    <label>Google API Key</label>
                    <input type="password" id="settingsGoogleKey" placeholder="...">
                    <button class="btn-save" data-provider="google">Save</button>
                </div>

                <h3>Git Repository</h3>
                <div class="form-group">
                    <label>Repository URL</label>
                    <input type="text" id="settingsGitUrl" placeholder="https://github.com/user/repo or /path/to/repo">
                    <button class="btn-save" id="btnSaveGit">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= $this->config['app']['base_url'] ?>/assets/js/game.js"></script>
</body>
</html>
