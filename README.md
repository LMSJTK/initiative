# Startup Game

A web-based simulation game where you're a member of a team building an app at a small startup. Work with AI-powered teammates using Claude, ChatGPT, or Gemini to build real projects collaboratively.

## Features

- **AI-Powered Teammates**: Each teammate is powered by your choice of AI models (Claude Sonnet 4.5, Claude Opus 4.5, ChatGPT 5.1, or Gemini 3.0)
- **Real Project Development**: The game creates actual git repositories and commits real code
- **Daily Workflow Simulation**:
  - Daily standups with AI teammates
  - Task generation and assignment
  - One-on-one chats with team members
  - Meetings and whiteboard sessions
  - End-of-day reports
- **Top-Down Office View**: Visual interface showing your office with desks and team members
- **Git Integration**: Real git repositories with feature branches and commit management
- **RAG-Powered Context**: AI teammates remember conversations and project context

## Tech Stack

- **Backend**: Vanilla PHP 8.0+ with PDO
- **Database**: MySQL 8.0+ (for game state and RAG context only)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **AI Integration**: Claude (Anthropic), ChatGPT (OpenAI), Gemini (Google)
- **Version Control**: Git

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache with mod_rewrite (or nginx)
- Git
- API keys for at least one AI provider:
  - Anthropic API key for Claude models
  - OpenAI API key for ChatGPT models
  - Google API key for Gemini models

### Step 1: Clone the Repository

```bash
# If deploying to a subdirectory on your LAMP server:
cd /var/www/html
git clone https://github.com/yourusername/startup-game.git
cd startup-game
```

### Step 2: Create the Database

```bash
mysql -u root -p < config/database.sql
```

Or manually:

```sql
CREATE DATABASE startup_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema:

```bash
mysql -u root -p startup_game < config/database.sql
```

### Step 3: Configure the Application

```bash
# Copy the example configuration
cp config/config.example.php config/config.php

# Edit the configuration file
nano config/config.php
```

Update the following settings in `config/config.php`:

```php
// Database configuration
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'startup_game',
    'username' => 'your_db_user',      // Change this
    'password' => 'your_db_password',  // Change this
    'charset' => 'utf8mb4'
],

// Application settings
'app' => [
    'base_url' => '/startup-game',  // Change if deploying to different path
    // ... other settings
],

// Git workspace (where project repos will be created)
'git' => [
    'workspace_path' => '/var/www/html/startup-game/workspace',
    // ... other settings
],

// IMPORTANT: Generate a random 32-character encryption key
'encryption_key' => 'your-random-32-character-key-here',
```

Generate a secure encryption key:

```bash
php -r "echo bin2hex(random_bytes(16)) . \"\n\";"
```

### Step 4: Set Permissions

```bash
# Create workspace directory for git repos
mkdir -p workspace
chmod 755 workspace

# Ensure Apache can write to workspace
chown -R www-data:www-data workspace

# Or for Amazon Linux 2023:
chown -R apache:apache workspace
```

### Step 5: Configure Apache

If deploying to a subdirectory, create/update `.htaccess`:

```bash
# This file is already included in the repository
cat public/.htaccess
```

The `.htaccess` file should contain:

```apache
RewriteEngine On
RewriteBase /startup-game/

# Don't rewrite files or directories
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Rewrite everything else to index.php
RewriteRule ^(.*)$ index.php [L,QSA]
```

Make sure mod_rewrite is enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2

# Or for Amazon Linux 2023:
sudo systemctl restart httpd
```

### Step 6: Access the Game

Open your browser and navigate to:

```
http://your-server.com/startup-game/
```

Or for local development:

```
http://localhost/startup-game/
```

## First-Time Setup

When you first access the game, you'll go through a setup wizard:

1. **Configure API Keys**: Enter your API keys for the AI providers you want to use
2. **Describe Your Project**: Tell the PM bot what you want to build
3. **Chat with PM**: Discuss your project idea with the AI Project Manager
4. **Choose Models**: Select which AI model to use for your PM and teammates
5. **Generate Team**: The PM will create a team with the right specialties

## Gameplay

### Daily Workflow

1. **Start Day**: Review the PM's daily overview and project progress
2. **Standup Meeting**: Give your update and receive tasks from the PM
3. **Work on Tasks**:
   - Chat with teammates to get work done
   - Teammates commit code to feature branches
   - Review and approve merges
4. **Meetings**: Attend scheduled meetings with multiple teammates
5. **End Day**: Review what was accomplished and get a summary

### Interacting with Teammates

- **Click on a teammate's circle** to open a one-on-one chat
- **Assign tasks** from the task panel to specific teammates
- **Review commits** in the commits tab
- **Join meetings** from the meetings tab

### Git Integration

The game can create real git repositories for your projects:

1. Click "Settings" in the top navigation
2. Either:
   - Initialize a new local repository, or
   - Link an existing GitHub repository
3. Teammates will create feature branches for their work
4. You approve merges to the main branch

**Note**: The git repository is separate from the game database. The database only stores game state and AI context, not your actual project code.

## Architecture

### Directory Structure

```
startup-game/
├── app/
│   ├── controllers/     # Game logic controllers
│   └── views/          # HTML templates
├── config/
│   ├── config.php      # Configuration (create from example)
│   ├── config.example.php
│   └── database.sql    # Database schema
├── public/
│   ├── assets/
│   │   ├── css/       # Stylesheets
│   │   └── js/        # JavaScript files
│   ├── .htaccess      # Apache rewrite rules
│   └── index.php      # Entry point
├── src/
│   ├── AI/            # AI provider integrations
│   ├── Models/        # Database models
│   └── Database.php   # Database connection
└── workspace/         # Git repositories (created at runtime)
```

### How It Works

1. **Game Database**: Stores game state, teammate configurations, conversations, and RAG context
2. **AI Integration**: Uses API calls to Claude, ChatGPT, or Gemini for teammate responses
3. **RAG System**: Stores conversation history and project context for AI teammates to reference
4. **Git Integration**: Creates real git repositories and commits for the in-game project
5. **Visual Office**: SVG-based top-down view of the office with interactive elements

### AI Models

Each teammate and the PM can use different AI models:

- **Claude Sonnet 4.5**: Fast, intelligent, good for most tasks
- **Claude Opus 4.5**: Most capable, best for PM and complex reasoning
- **ChatGPT 5.1**: OpenAI's latest model
- **Gemini 3.0**: Google's latest model

Different teammates will have different specialties (frontend, backend, design, DevOps, QA) and personalities.

## Security Notes

- **API Keys**: Stored encrypted in the database using AES-256-CBC
- **Never commit** your `config/config.php` file with real credentials
- **Generate a secure encryption key** for production use
- **Restrict database access** to localhost if possible
- **Use HTTPS** in production to protect API keys in transit

## Troubleshooting

### Database Connection Failed

- Check your database credentials in `config/config.php`
- Ensure MySQL is running: `sudo systemctl status mysql`
- Verify the database exists: `mysql -u root -p -e "SHOW DATABASES;"`

### API Key Errors

- Verify your API keys are correct and active
- Check API provider status and rate limits
- Ensure the encryption key hasn't changed (would invalidate stored keys)

### Git Operations Failing

- Check workspace directory permissions
- Ensure git is installed: `git --version`
- Verify the workspace path in `config/config.php`

### 404 Errors

- Ensure mod_rewrite is enabled
- Check `.htaccess` file exists in `public/` directory
- Verify `base_url` in config matches your subdirectory path

### Blank Page / PHP Errors

- Check PHP error logs: `tail -f /var/log/apache2/error.log`
- Enable debug mode in `config/config.php`: `'debug' => true`
- Ensure PHP 8.0+ is installed: `php -v`

## Development

### Adding New AI Providers

1. Create a new provider class in `src/AI/` implementing the `AIProvider` interface
2. Add provider configuration to `config/config.php`
3. Update `AIFactory` to handle the new provider

### Customizing Gameplay

- Modify bot behavior in `src/AI/BotManager.php`
- Adjust game phases in controllers
- Customize office layout in `config/config.php` and update SVG in `game.php`

## Contributing

This is a game project for learning and experimentation. Feel free to fork and modify!

## License

MIT License - feel free to use and modify for your own projects.

## Credits

Built with:
- Anthropic Claude
- OpenAI ChatGPT
- Google Gemini
- PHP, MySQL, and vanilla JavaScript

## Support

For issues and questions, please open an issue on GitHub or consult the troubleshooting section above.

---

**Have fun building with your AI team!** 🚀
