<?php

use StartupGame\Models\Project;

/**
 * Home Controller - Main landing page
 */
class HomeController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function index(): void
    {
        // Check if user has an active project
        $projectId = $_SESSION['project_id'] ?? null;
        $project = null;

        if ($projectId) {
            $project = Project::find($projectId);
        }

        // If no active project, show setup
        if (!$project) {
            $this->showSetup();
            return;
        }

        // Show game interface
        $this->showGame($project);
    }

    private function showSetup(): void
    {
        require __DIR__ . '/../views/setup.php';
    }

    private function showGame(Project $project): void
    {
        require __DIR__ . '/../views/game.php';
    }
}
