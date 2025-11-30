<?php
/**
 * Startup Game Configuration
 * Copy this file to config.php and update with your settings
 */

return [
    // Database configuration
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'startup_game',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'charset' => 'utf8mb4'
    ],

    // Application settings
    'app' => [
        'name' => 'Startup Game',
        'base_url' => '/startup-game', // Subdirectory path on your server
        'timezone' => 'America/New_York',
        'debug' => true,
        'session_lifetime' => 7200 // 2 hours in seconds
    ],

    // Git configuration
    'git' => [
        'enabled' => true,
        'workspace_path' => '/var/www/html/startup-game/workspace', // Where game project repos will be created
        'default_branch' => 'main'
    ],

    // AI Model providers (API keys will be stored encrypted in DB per project)
    'ai_providers' => [
        'openai' => [
            'enabled' => true,
            'models' => [
                'gpt-5.1' => [
                    'name' => 'ChatGPT 5.1',
                    'api_endpoint' => 'https://api.openai.com/v1/chat/completions',
                    'max_tokens' => 4096
                ]
            ]
        ],
        'anthropic' => [
            'enabled' => true,
            'models' => [
                'claude-sonnet-4.5' => [
                    'name' => 'Claude Sonnet 4.5',
                    'api_endpoint' => 'https://api.anthropic.com/v1/messages',
                    'max_tokens' => 4096
                ],
                'claude-opus-4.5' => [
                    'name' => 'Claude Opus 4.5',
                    'api_endpoint' => 'https://api.anthropic.com/v1/messages',
                    'max_tokens' => 4096
                ]
            ]
        ],
        'google' => [
            'enabled' => true,
            'models' => [
                'gemini-3.0' => [
                    'name' => 'Gemini 3.0',
                    'api_endpoint' => 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent',
                    'max_tokens' => 4096
                ]
            ]
        ]
    ],

    // Encryption key for storing API keys (generate a random 32-character string)
    'encryption_key' => 'CHANGE_THIS_TO_RANDOM_32_CHARS!!',

    // Office layout defaults (in grid units)
    'office' => [
        'width' => 1200,
        'height' => 800,
        'desk_width' => 80,
        'desk_height' => 60,
        'player_desk' => ['x' => 100, 'y' => 100],
        'conference_room' => ['x' => 800, 'y' => 100, 'width' => 300, 'height' => 200]
    ],

    // Game settings
    'game' => [
        'minutes_per_activity' => 30, // Time block size
        'max_activities_per_day' => 8, // 4 hours of workday
        'available_colors' => [
            '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
            '#9b59b6', '#1abc9c', '#e67e22', '#34495e'
        ]
    ]
];
