// Setup Page JavaScript

const basePath = window.location.pathname.split('/').slice(0, -1).join('/');

// Step navigation
let currentStep = 1;
const apiKeys = {};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
});

function setupEventListeners() {
    // API key checkboxes
    document.getElementById('useAnthropic').addEventListener('change', (e) => {
        document.getElementById('anthropicKey').disabled = !e.target.checked;
        document.querySelector('[data-provider="anthropic"]').disabled = !e.target.checked;
    });

    document.getElementById('useOpenAI').addEventListener('change', (e) => {
        document.getElementById('openaiKey').disabled = !e.target.checked;
        document.querySelector('[data-provider="openai"]').disabled = !e.target.checked;
    });

    document.getElementById('useGoogle').addEventListener('change', (e) => {
        document.getElementById('googleKey').disabled = !e.target.checked;
        document.querySelector('[data-provider="google"]').disabled = !e.target.checked;
    });

    // Test API key buttons
    document.querySelectorAll('.btn-test').forEach(btn => {
        btn.addEventListener('click', () => testApiKey(btn.dataset.provider));
    });

    // Next to step 2
    document.getElementById('btnNextToStep2').addEventListener('click', () => {
        if (saveApiKeys()) {
            showStep(2);
        }
    });

    // Start chat with PM
    document.getElementById('btnStartChat').addEventListener('click', startPMChat);

    // Send message in chat
    document.getElementById('btnSendMessage').addEventListener('click', sendMessage);
    document.getElementById('chatMessage').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Finalize setup
    document.getElementById('btnFinalizeSetup').addEventListener('click', finalizeSetup);
}

async function testApiKey(provider) {
    const keyInput = document.getElementById(`${provider}Key`);
    const apiKey = keyInput.value.trim();

    if (!apiKey) {
        alert('Please enter an API key');
        return;
    }

    const providerMap = {
        'anthropic': 'anthropic',
        'openai': 'openai',
        'google': 'google'
    };

    try {
        const response = await fetch(`${basePath}/api/settings`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'test_api_key',
                provider: providerMap[provider],
                api_key: apiKey
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('API key is valid!');
            apiKeys[provider] = apiKey;
        } else {
            alert('API key test failed: ' + data.error);
        }
    } catch (error) {
        alert('Error testing API key: ' + error.message);
    }
}

function saveApiKeys() {
    const providers = ['anthropic', 'openai', 'google'];
    let hasAtLeastOne = false;

    providers.forEach(provider => {
        const checkbox = document.getElementById(`use${provider.charAt(0).toUpperCase() + provider.slice(1)}`);
        const input = document.getElementById(`${provider}Key`);

        if (checkbox.checked) {
            const key = input.value.trim();
            if (key) {
                apiKeys[provider] = key;
                hasAtLeastOne = true;
            }
        }
    });

    if (!hasAtLeastOne) {
        alert('Please configure at least one API key');
        return false;
    }

    return true;
}

async function startPMChat() {
    const playerName = document.getElementById('playerName').value.trim();
    const projectIdea = document.getElementById('projectIdea').value.trim();

    if (!playerName || !projectIdea) {
        alert('Please enter your name and project idea');
        return;
    }

    try {
        // Save all API keys first
        for (const [provider, key] of Object.entries(apiKeys)) {
            await fetch(`${basePath}/api/setup`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_api_key',
                    provider: provider,
                    api_key: key
                })
            });
        }

        // Create project
        const response = await fetch(`${basePath}/api/setup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'create_project',
                player_name: playerName,
                project_idea: projectIdea
            })
        });

        const data = await response.json();

        if (data.success) {
            showStep(3);
            // Send initial message to PM
            await sendMessage(`Hi! I want to build: ${projectIdea}`);
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function sendMessage(customMessage) {
    const messageInput = document.getElementById('chatMessage');
    const message = customMessage || messageInput.value.trim();

    if (!message) return;

    // Add user message to chat
    addMessageToChat(message, true);

    if (!customMessage) {
        messageInput.value = '';
    }

    try {
        const response = await fetch(`${basePath}/api/setup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'chat_with_pm',
                message: message
            })
        });

        const data = await response.json();

        if (data.success) {
            addMessageToChat(data.response, false, 'PM: Alex');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function addMessageToChat(message, isPlayer, senderName = 'You') {
    const chatContainer = document.getElementById('chatContainer');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${isPlayer ? 'player' : 'bot'}`;

    const senderDiv = document.createElement('div');
    senderDiv.className = 'sender';
    senderDiv.textContent = senderName;

    const contentDiv = document.createElement('div');
    contentDiv.textContent = message;

    messageDiv.appendChild(senderDiv);
    messageDiv.appendChild(contentDiv);
    chatContainer.appendChild(messageDiv);

    chatContainer.scrollTop = chatContainer.scrollHeight;
}

async function finalizeSetup() {
    const pmModel = document.getElementById('pmModel').value;
    const assistantModel = document.getElementById('assistantModel').value;

    showStep(4);

    try {
        const response = await fetch(`${basePath}/api/setup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'finalize_setup',
                pm_model: pmModel,
                assistant_model: assistantModel
            })
        });

        const data = await response.json();

        if (data.success) {
            // Redirect to game
            window.location.href = basePath + '/';
        } else {
            alert('Error: ' + data.error);
            showStep(3);
        }
    } catch (error) {
        alert('Error: ' + error.message);
        showStep(3);
    }
}

function showStep(step) {
    document.querySelectorAll('.setup-step').forEach(s => s.classList.remove('active'));
    document.getElementById(`step${step}`).classList.add('active');
    currentStep = step;
}
