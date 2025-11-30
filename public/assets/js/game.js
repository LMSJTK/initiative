// Game Page JavaScript

// Get base path, removing 'public' if present in URL
let basePath = window.location.pathname.split('/').slice(0, -1).join('/');
basePath = basePath.replace(/\/public$/, '');

let gameState = {
    project: null,
    teammates: [],
    tasks: [],
    meetings: [],
    currentDay: 0
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    loadGameState();
});

function setupEventListeners() {
    // Action buttons
    document.getElementById('btnStartDay').addEventListener('click', startDay);
    document.getElementById('btnStandup').addEventListener('click', startStandup);
    document.getElementById('btnEndDay').addEventListener('click', endDay);

    // Settings
    document.getElementById('btnSettings').addEventListener('click', openSettings);

    // Close overlay
    document.getElementById('btnCloseOverlay').addEventListener('click', closeOverlay);

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Save API key buttons in settings
    document.querySelectorAll('.btn-save[data-provider]').forEach(btn => {
        btn.addEventListener('click', () => saveApiKey(btn.dataset.provider));
    });

    document.getElementById('btnSaveGit').addEventListener('click', saveGitUrl);
}

async function loadGameState() {
    try {
        const response = await fetch(`${basePath}/api/game?action=get_state`);
        const data = await response.json();

        if (data.success) {
            gameState = data;
            updateUI();
            renderOffice();
        } else {
            console.error('Failed to load game state:', data.error);
        }
    } catch (error) {
        console.error('Error loading game state:', error);
    }
}

function updateUI() {
    // Update header
    document.getElementById('currentDay').textContent = gameState.project.current_day;
    document.getElementById('currentPhase').textContent = gameState.project.current_phase.replace('_', ' ');

    // Update action buttons based on phase
    const phase = gameState.project.current_phase;

    document.getElementById('btnStartDay').disabled = phase !== 'day_end' && gameState.project.current_day > 0;
    document.getElementById('btnStandup').disabled = phase !== 'standup';
    document.getElementById('btnEndDay').disabled = phase !== 'working';

    // Update tasks list
    renderTasks();

    // Update meetings list
    renderMeetings();
}

function renderOffice() {
    const svg = document.getElementById('officeSvg');

    // Clear existing teammate elements
    const existingTeammates = svg.querySelectorAll('.teammate-circle, .teammate-label');
    existingTeammates.forEach(el => el.remove());

    // Render each teammate
    gameState.teammates.forEach(teammate => {
        // Create circle
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', teammate.position.x);
        circle.setAttribute('cy', teammate.position.y);
        circle.setAttribute('r', 25);
        circle.setAttribute('fill', teammate.color);
        circle.setAttribute('class', 'teammate-circle');
        circle.setAttribute('data-teammate-id', teammate.id);
        circle.style.cursor = 'pointer';

        circle.addEventListener('click', () => openTeammateChat(teammate));

        // Create label
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('x', teammate.position.x);
        text.setAttribute('y', teammate.position.y + 45);
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('class', 'teammate-label');
        text.textContent = teammate.name;

        svg.appendChild(circle);
        svg.appendChild(text);
    });
}

function renderTasks() {
    const tasksList = document.getElementById('tasksList');
    tasksList.innerHTML = '';

    if (gameState.tasks.length === 0) {
        tasksList.innerHTML = '<p class="empty-state">No tasks yet. Start your day to get tasks!</p>';
        return;
    }

    gameState.tasks.forEach(task => {
        const taskDiv = document.createElement('div');
        taskDiv.className = 'task-item';
        taskDiv.innerHTML = `
            <h4>${task.title}</h4>
            <p>${task.description || 'No description'}</p>
            <div>
                <span class="priority ${task.priority}">${task.priority}</span>
                <span class="status ${task.status}">${task.status.replace('_', ' ')}</span>
            </div>
            <button class="btn-task" data-task-id="${task.id}">Work on this</button>
        `;

        taskDiv.querySelector('.btn-task').addEventListener('click', () => workOnTask(task));

        tasksList.appendChild(taskDiv);
    });
}

function renderMeetings() {
    const meetingsList = document.getElementById('meetingsList');
    meetingsList.innerHTML = '';

    if (gameState.meetings.length === 0) {
        meetingsList.innerHTML = '<p class="empty-state">No meetings scheduled.</p>';
        return;
    }

    gameState.meetings.forEach(meeting => {
        const meetingDiv = document.createElement('div');
        meetingDiv.className = 'meeting-item';
        meetingDiv.innerHTML = `
            <h4>${meeting.topic || meeting.type}</h4>
            <p>Status: ${meeting.status}</p>
            <p>Participants: ${meeting.participants.length}</p>
            ${meeting.status === 'scheduled' ? '<button class="btn-meeting">Join</button>' : ''}
        `;

        if (meeting.status === 'scheduled') {
            meetingDiv.querySelector('.btn-meeting').addEventListener('click', () => joinMeeting(meeting));
        }

        meetingsList.appendChild(meetingDiv);
    });
}

async function startDay() {
    try {
        const response = await fetch(`${basePath}/api/game`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'start_day' })
        });

        const data = await response.json();

        if (data.success) {
            showOverlay('Day ' + data.day, `<div class="chat-container"><p>${data.overview}</p></div>`);
            await loadGameState();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function startStandup() {
    const playerUpdate = prompt('Your standup update:', 'Working on implementing features...');

    if (!playerUpdate) return;

    try {
        const response = await fetch(`${basePath}/api/game`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'start_standup',
                player_update: playerUpdate
            })
        });

        const data = await response.json();

        if (data.success) {
            let content = '<div class="chat-container">';
            content += `<h3>Standup Notes:</h3>`;
            content += `<pre>${data.standup_notes}</pre>`;
            content += `<h3>Tasks Generated:</h3>`;
            content += `<ul>`;
            data.tasks.forEach(task => {
                content += `<li><strong>${task.title}</strong>: ${task.description}</li>`;
            });
            content += `</ul></div>`;

            showOverlay('Standup Complete', content);
            await loadGameState();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function endDay() {
    try {
        const response = await fetch(`${basePath}/api/game`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'end_day' })
        });

        const data = await response.json();

        if (data.success) {
            showOverlay('Day Complete', `
                <div class="chat-container">
                    <h3>Summary:</h3>
                    <p>${data.summary}</p>
                    <p><strong>Tasks Completed:</strong> ${data.tasks_completed}</p>
                </div>
            `);
            await loadGameState();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function openTeammateChat(teammate) {
    const content = `
        <div id="chatArea" class="chat-container" style="height: 400px; overflow-y: auto; margin-bottom: 20px;"></div>
        <div class="chat-input">
            <input type="text" id="teammateMessage" placeholder="Type your message...">
            <button id="btnSendTeammateMessage" class="btn-primary">Send</button>
        </div>
    `;

    showOverlay(`Chat with ${teammate.name}`, content);

    // Load chat history
    await loadChatHistory(teammate.id);

    // Setup send button
    document.getElementById('btnSendTeammateMessage').addEventListener('click', () => sendTeammateMessage(teammate.id));
    document.getElementById('teammateMessage').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendTeammateMessage(teammate.id);
    });
}

async function loadChatHistory(teammateId) {
    try {
        const response = await fetch(`${basePath}/api/chat?action=get_history&teammate_id=${teammateId}`);
        const data = await response.json();

        const chatArea = document.getElementById('chatArea');
        chatArea.innerHTML = '';

        if (data.success && data.history.length > 0) {
            data.history.forEach(msg => {
                addChatMessage(msg.message, msg.is_player);
            });
        }
    } catch (error) {
        console.error('Error loading chat history:', error);
    }
}

async function sendTeammateMessage(teammateId) {
    const input = document.getElementById('teammateMessage');
    const message = input.value.trim();

    if (!message) return;

    addChatMessage(message, true);
    input.value = '';

    try {
        const response = await fetch(`${basePath}/api/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'send_message',
                teammate_id: teammateId,
                message: message
            })
        });

        const data = await response.json();

        if (data.success) {
            addChatMessage(data.response, false, data.teammate.name);
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function addChatMessage(message, isPlayer, senderName = 'You') {
    const chatArea = document.getElementById('chatArea');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${isPlayer ? 'player' : 'bot'}`;

    const senderDiv = document.createElement('div');
    senderDiv.className = 'sender';
    senderDiv.textContent = isPlayer ? 'You' : senderName;

    const contentDiv = document.createElement('div');
    contentDiv.textContent = message;

    messageDiv.appendChild(senderDiv);
    messageDiv.appendChild(contentDiv);
    chatArea.appendChild(messageDiv);

    chatArea.scrollTop = chatArea.scrollHeight;
}

async function workOnTask(task) {
    const teammate = gameState.teammates.find(t => t.id === task.assigned_to);

    if (!teammate) {
        alert('No teammate assigned to this task');
        return;
    }

    openTeammateChat(teammate);
}

async function joinMeeting(meeting) {
    showOverlay(`Meeting: ${meeting.topic}`, '<div class="chat-container"><p>Meeting functionality coming soon...</p></div>');
}

function showOverlay(title, content) {
    document.getElementById('overlayTitle').textContent = title;
    document.getElementById('overlayContent').innerHTML = content;
    document.getElementById('interactionOverlay').classList.remove('hidden');
}

function closeOverlay() {
    document.getElementById('interactionOverlay').classList.add('hidden');
}

function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));

    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`${tabName}Panel`).classList.add('active');
}

function openSettings() {
    document.getElementById('settingsModal').classList.remove('hidden');
}

function closeSettings() {
    document.getElementById('settingsModal').classList.add('hidden');
}

async function saveApiKey(provider) {
    const keyInput = document.getElementById(`settings${provider.charAt(0).toUpperCase() + provider.slice(1)}Key`);
    const apiKey = keyInput.value.trim();

    if (!apiKey) {
        alert('Please enter an API key');
        return;
    }

    try {
        const response = await fetch(`${basePath}/api/settings`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'save_api_key',
                provider: provider,
                api_key: apiKey
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('API key saved successfully');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function saveGitUrl() {
    const gitUrl = document.getElementById('settingsGitUrl').value.trim();

    if (!gitUrl) {
        alert('Please enter a git repository URL');
        return;
    }

    try {
        const response = await fetch(`${basePath}/api/git`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'link_repo',
                repo_url: gitUrl
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('Git repository linked successfully');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Make closeSettings available globally
window.closeSettings = closeSettings;
