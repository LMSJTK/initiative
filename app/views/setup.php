<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Startup Game</title>
    <link rel="stylesheet" href="<?= $this->config['app']['base_url'] ?>/assets/css/style.css">
</head>
<body class="setup-page">
    <div class="container">
        <header>
            <h1>Startup Game</h1>
            <p>Build your dream project with an AI-powered team</p>
        </header>

        <main id="setupContainer">
            <!-- Step 1: API Keys -->
            <div id="step1" class="setup-step active">
                <h2>Step 1: Configure API Keys</h2>
                <p>Enter API keys for the AI models you want to use:</p>

                <div class="api-key-form">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="useAnthropic"> Anthropic (Claude)
                        </label>
                        <input type="password" id="anthropicKey" placeholder="sk-ant-..." disabled>
                        <button class="btn-test" data-provider="anthropic" disabled>Test</button>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="useOpenAI"> OpenAI (ChatGPT)
                        </label>
                        <input type="password" id="openaiKey" placeholder="sk-..." disabled>
                        <button class="btn-test" data-provider="openai" disabled>Test</button>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="useGoogle"> Google (Gemini)
                        </label>
                        <input type="password" id="googleKey" placeholder="..." disabled>
                        <button class="btn-test" data-provider="google" disabled>Test</button>
                    </div>
                </div>

                <button id="btnNextToStep2" class="btn-primary">Next</button>
            </div>

            <!-- Step 2: Project Idea -->
            <div id="step2" class="setup-step">
                <h2>Step 2: What do you want to build?</h2>
                <p>Your name:</p>
                <input type="text" id="playerName" placeholder="Your name" value="Player">

                <p>Describe your project idea:</p>
                <textarea id="projectIdea" rows="6" placeholder="I want to build a task management app that helps teams collaborate..."></textarea>

                <button id="btnStartChat" class="btn-primary">Chat with PM</button>
            </div>

            <!-- Step 3: PM Chat -->
            <div id="step3" class="setup-step">
                <h2>Step 3: Discuss with Project Manager</h2>

                <div class="model-selector">
                    <label>PM Model:</label>
                    <select id="pmModel">
                        <option value="claude-sonnet-4.5">Claude Sonnet 4.5</option>
                        <option value="claude-opus-4.5">Claude Opus 4.5</option>
                        <option value="chatgpt-5.1">ChatGPT 5.1</option>
                        <option value="gemini-3.0">Gemini 3.0</option>
                    </select>

                    <label>Your Assistant Model:</label>
                    <select id="assistantModel">
                        <option value="claude-sonnet-4.5">Claude Sonnet 4.5</option>
                        <option value="claude-opus-4.5">Claude Opus 4.5</option>
                        <option value="chatgpt-5.1">ChatGPT 5.1</option>
                        <option value="gemini-3.0">Gemini 3.0</option>
                    </select>
                </div>

                <div id="chatContainer" class="chat-container"></div>

                <div class="chat-input">
                    <input type="text" id="chatMessage" placeholder="Type your message...">
                    <button id="btnSendMessage" class="btn-primary">Send</button>
                </div>

                <button id="btnFinalizeSetup" class="btn-primary">Finalize Team & Start</button>
            </div>

            <!-- Step 4: Loading -->
            <div id="step4" class="setup-step">
                <h2>Generating your team...</h2>
                <div class="loader"></div>
            </div>
        </main>
    </div>

    <script src="<?= $this->config['app']['base_url'] ?>/assets/js/setup.js"></script>
</body>
</html>
