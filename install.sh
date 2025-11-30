#!/bin/bash

# Startup Game Installation Script
# Run this script to set up the game on your server

echo "=================================="
echo "Startup Game Installation"
echo "=================================="
echo ""

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ]; then
    echo "ERROR: PHP 8.0 or higher is required. You have PHP $PHP_VERSION"
    exit 1
fi

echo "✓ PHP $PHP_VERSION detected"
echo ""

# Check MySQL
echo "Checking MySQL/MariaDB..."
if ! command -v mysql &> /dev/null; then
    echo "ERROR: MySQL/MariaDB not found. Please install MySQL first."
    exit 1
fi

echo "✓ MySQL detected"
echo ""

# Check Git
echo "Checking Git..."
if ! command -v git &> /dev/null; then
    echo "ERROR: Git not found. Please install Git first."
    exit 1
fi

echo "✓ Git detected"
echo ""

# Create workspace directory
echo "Creating workspace directory..."
mkdir -p workspace
chmod 755 workspace

# Detect web server user
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
else
    WEB_USER=$(whoami)
    echo "WARNING: Could not detect web server user. Using current user: $WEB_USER"
fi

echo "Setting permissions for web server user: $WEB_USER"
chown -R $WEB_USER:$WEB_USER workspace 2>/dev/null || {
    echo "NOTE: Could not change ownership. You may need to run:"
    echo "  sudo chown -R $WEB_USER:$WEB_USER workspace"
}

echo "✓ Workspace directory created"
echo ""

# Copy configuration
if [ ! -f config/config.php ]; then
    echo "Creating configuration file..."
    cp config/config.example.php config/config.php
    echo "✓ Configuration file created"
    echo ""
    echo "⚠️  IMPORTANT: Edit config/config.php and update:"
    echo "   - Database credentials"
    echo "   - Base URL (if not /startup-game)"
    echo "   - Encryption key (generate with: php -r \"echo bin2hex(random_bytes(16));\")"
    echo ""
else
    echo "✓ Configuration file already exists"
    echo ""
fi

# Database setup
echo "Do you want to set up the database now? (y/n)"
read -r SETUP_DB

if [ "$SETUP_DB" = "y" ]; then
    echo ""
    echo "Enter MySQL root password:"
    read -s MYSQL_ROOT_PASS
    echo ""

    echo "Enter database name (default: startup_game):"
    read -r DB_NAME
    DB_NAME=${DB_NAME:-startup_game}

    echo "Enter database user (default: startup_game):"
    read -r DB_USER
    DB_USER=${DB_USER:-startup_game}

    echo "Enter database password:"
    read -s DB_PASS
    echo ""

    echo "Creating database and user..."

    mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        echo "✓ Database created successfully"
        echo ""

        echo "Importing schema..."
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < config/database.sql

        if [ $? -eq 0 ]; then
            echo "✓ Schema imported successfully"
            echo ""
            echo "⚠️  Remember to update config/config.php with these credentials:"
            echo "   Database: $DB_NAME"
            echo "   Username: $DB_USER"
            echo "   Password: (the one you just entered)"
        else
            echo "ERROR: Failed to import schema"
            exit 1
        fi
    else
        echo "ERROR: Failed to create database"
        exit 1
    fi
fi

echo ""
echo "=================================="
echo "Installation Complete!"
echo "=================================="
echo ""
echo "Next steps:"
echo "1. Edit config/config.php with your settings"
echo "2. Get API keys from:"
echo "   - Anthropic: https://console.anthropic.com/"
echo "   - OpenAI: https://platform.openai.com/"
echo "   - Google: https://makersuite.google.com/"
echo "3. Access the game at: http://your-server/startup-game/"
echo ""
echo "For detailed instructions, see README.md"
echo ""
