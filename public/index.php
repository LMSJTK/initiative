<?php
/**
 * Startup Game - Main entry point
 */

// Start session
session_start();

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'StartupGame\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die('Configuration file not found. Please copy config.example.php to config.php and update settings.');
}

$config = require $configFile;

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Initialize database
use StartupGame\Database;
Database::init($config['database']);

// Simple routing
$request = $_SERVER['REQUEST_URI'];
$basePath = $config['app']['base_url'];

// Remove base path and query string
$path = str_replace($basePath, '', parse_url($request, PHP_URL_PATH));
$path = trim($path, '/');

// Route to appropriate controller
try {
    if (empty($path) || $path === 'index.php') {
        require __DIR__ . '/../app/controllers/HomeController.php';
        (new HomeController($config))->index();
    } elseif ($path === 'api/setup') {
        require __DIR__ . '/../app/controllers/SetupController.php';
        (new SetupController($config))->handle();
    } elseif ($path === 'api/game') {
        require __DIR__ . '/../app/controllers/GameController.php';
        (new GameController($config))->handle();
    } elseif ($path === 'api/chat') {
        require __DIR__ . '/../app/controllers/ChatController.php';
        (new ChatController($config))->handle();
    } elseif ($path === 'api/settings') {
        require __DIR__ . '/../app/controllers/SettingsController.php';
        (new SettingsController($config))->handle();
    } elseif ($path === 'api/git') {
        require __DIR__ . '/../app/controllers/GitController.php';
        (new GitController($config))->handle();
    } elseif (strpos($path, 'assets/') === 0) {
        // Serve static assets
        $file = __DIR__ . '/' . $path;
        if (file_exists($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml'
            ];
            if (isset($mimeTypes[$ext])) {
                header('Content-Type: ' . $mimeTypes[$ext]);
                readfile($file);
                exit;
            }
        }
        http_response_code(404);
        echo 'File not found';
    } else {
        http_response_code(404);
        echo '404 - Page not found';
    }
} catch (Exception $e) {
    if ($config['app']['debug']) {
        echo '<pre>Error: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString() . '</pre>';
    } else {
        http_response_code(500);
        echo 'An error occurred. Please try again.';
    }
}
