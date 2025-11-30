<?php

use StartupGame\Models\Project;
use StartupGame\Models\Task;
use StartupGame\Database;

/**
 * Git Controller - Handles git operations and integration
 */
class GitController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'init_repo':
                    $this->initRepository();
                    break;

                case 'link_repo':
                    $this->linkRepository();
                    break;

                case 'create_branch':
                    $this->createBranch();
                    break;

                case 'commit':
                    $this->commit();
                    break;

                case 'push':
                    $this->push();
                    break;

                case 'get_branches':
                    $this->getBranches();
                    break;

                case 'get_commits':
                    $this->getCommits();
                    break;

                case 'approve_merge':
                    $this->approveMerge();
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Initialize a new git repository for the project
     */
    private function initRepository(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        // Create workspace directory if it doesn't exist
        $workspacePath = $this->config['git']['workspace_path'];
        if (!file_exists($workspacePath)) {
            mkdir($workspacePath, 0755, true);
        }

        // Create project directory
        $projectPath = $workspacePath . '/' . $this->sanitizeProjectName($project->projectName);

        if (file_exists($projectPath)) {
            throw new Exception('Repository already exists');
        }

        mkdir($projectPath, 0755, true);

        // Initialize git
        $commands = [
            "cd '$projectPath'",
            "git init",
            "git config user.name 'Startup Game'",
            "git config user.email 'game@startup.local'",
            "echo '# {$project->projectName}' > README.md",
            "echo '{$project->projectDescription}' >> README.md",
            "git add README.md",
            "git commit -m 'Initial commit'"
        ];

        $output = $this->executeGitCommands($commands);

        // Update project with repo path
        $project->gitRepoUrl = $projectPath;
        $project->save();

        echo json_encode([
            'success' => true,
            'repo_path' => $projectPath,
            'output' => $output
        ]);
    }

    /**
     * Link an existing git repository
     */
    private function linkRepository(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $repoUrl = $_POST['repo_url'] ?? '';

        if (empty($repoUrl)) {
            throw new Exception('Repository URL is required');
        }

        // Update project
        $project->gitRepoUrl = $repoUrl;
        $project->save();

        echo json_encode([
            'success' => true,
            'repo_url' => $repoUrl
        ]);
    }

    /**
     * Create a new branch for a task
     */
    private function createBranch(): void
    {
        $taskId = $_POST['task_id'] ?? null;

        if (!$taskId) {
            throw new Exception('Task ID is required');
        }

        $task = Task::find($taskId);
        if (!$task) {
            throw new Exception('Task not found');
        }

        $project = Project::find($task->projectId);
        if (!$project->gitRepoUrl) {
            throw new Exception('No git repository configured');
        }

        // Create branch name
        $branchName = 'feature/task-' . $task->id . '-' . $this->slugify($task->title);

        $commands = [
            "cd '{$project->gitRepoUrl}'",
            "git checkout -b '$branchName'"
        ];

        $output = $this->executeGitCommands($commands);

        // Update task with branch name
        $task->gitBranch = $branchName;
        $task->save();

        echo json_encode([
            'success' => true,
            'branch_name' => $branchName,
            'output' => $output
        ]);
    }

    /**
     * Create a commit
     */
    private function commit(): void
    {
        $taskId = $_POST['task_id'] ?? null;
        $message = $_POST['message'] ?? '';
        $files = $_POST['files'] ?? '';

        if (!$taskId || empty($message)) {
            throw new Exception('Task ID and commit message are required');
        }

        $task = Task::find($taskId);
        if (!$task) {
            throw new Exception('Task not found');
        }

        $project = Project::find($task->projectId);
        if (!$project->gitRepoUrl) {
            throw new Exception('No git repository configured');
        }

        $commands = [
            "cd '{$project->gitRepoUrl}'",
            "git add .",
            "git commit -m '$message'"
        ];

        $output = $this->executeGitCommands($commands);

        // Get commit hash
        $hashCmd = ["cd '{$project->gitRepoUrl}'", "git rev-parse HEAD"];
        $hashOutput = $this->executeGitCommands($hashCmd);
        $commitHash = trim($hashOutput);

        // Save commit to database
        $sql = "INSERT INTO git_commits (project_id, task_id, branch_name, commit_hash, commit_message, author_id)
                VALUES (:project_id, :task_id, :branch_name, :commit_hash, :commit_message, :author_id)";

        Database::execute($sql, [
            ':project_id' => $project->id,
            ':task_id' => $task->id,
            ':branch_name' => $task->gitBranch,
            ':commit_hash' => $commitHash,
            ':commit_message' => $message,
            ':author_id' => $task->assignedTo
        ]);

        echo json_encode([
            'success' => true,
            'commit_hash' => $commitHash,
            'output' => $output
        ]);
    }

    /**
     * Push commits to remote
     */
    private function push(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project->gitRepoUrl) {
            throw new Exception('No git repository configured');
        }

        $branch = $_POST['branch'] ?? 'main';

        $commands = [
            "cd '{$project->gitRepoUrl}'",
            "git push origin '$branch'"
        ];

        $output = $this->executeGitCommands($commands);

        echo json_encode([
            'success' => true,
            'output' => $output
        ]);
    }

    /**
     * Get branches
     */
    private function getBranches(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $project = Project::find($projectId);
        if (!$project->gitRepoUrl) {
            throw new Exception('No git repository configured');
        }

        $commands = [
            "cd '{$project->gitRepoUrl}'",
            "git branch -a"
        ];

        $output = $this->executeGitCommands($commands);
        $branches = array_filter(explode("\n", $output));

        echo json_encode([
            'success' => true,
            'branches' => $branches
        ]);
    }

    /**
     * Get commits for project
     */
    private function getCommits(): void
    {
        $projectId = $_SESSION['project_id'] ?? null;
        if (!$projectId) {
            throw new Exception('No active project');
        }

        $sql = "SELECT gc.*, t.title as task_title, tm.name as author_name
                FROM git_commits gc
                LEFT JOIN tasks t ON gc.task_id = t.id
                LEFT JOIN teammates tm ON gc.author_id = tm.id
                WHERE gc.project_id = :project_id
                ORDER BY gc.created_at DESC
                LIMIT 50";

        $commits = Database::query($sql, [':project_id' => $projectId]);

        echo json_encode([
            'success' => true,
            'commits' => array_map(fn($c) => [
                'id' => $c['id'],
                'hash' => $c['commit_hash'],
                'message' => $c['commit_message'],
                'branch' => $c['branch_name'],
                'task' => $c['task_title'],
                'author' => $c['author_name'],
                'is_merged' => (bool)$c['is_merged'],
                'timestamp' => $c['created_at']
            ], $commits)
        ]);
    }

    /**
     * Approve and merge a commit
     */
    private function approveMerge(): void
    {
        $commitId = $_POST['commit_id'] ?? null;

        if (!$commitId) {
            throw new Exception('Commit ID is required');
        }

        $commit = Database::queryOne("SELECT * FROM git_commits WHERE id = ?", [$commitId]);
        if (!$commit) {
            throw new Exception('Commit not found');
        }

        $project = Project::find($commit['project_id']);
        if (!$project->gitRepoUrl) {
            throw new Exception('No git repository configured');
        }

        // Merge branch to main
        $mainBranch = $this->config['git']['default_branch'];
        $commands = [
            "cd '{$project->gitRepoUrl}'",
            "git checkout '$mainBranch'",
            "git merge '{$commit['branch_name']}'",
            "git branch -d '{$commit['branch_name']}'"
        ];

        $output = $this->executeGitCommands($commands);

        // Update commit as merged
        $sql = "UPDATE git_commits SET is_merged = 1, merge_approved_at = NOW() WHERE id = ?";
        Database::execute($sql, [$commitId]);

        echo json_encode([
            'success' => true,
            'output' => $output
        ]);
    }

    /**
     * Helper methods
     */

    private function executeGitCommands(array $commands): string
    {
        $command = implode(' && ', $commands);
        $output = shell_exec($command . ' 2>&1');
        return $output ?? '';
    }

    private function sanitizeProjectName(string $name): string
    {
        return preg_replace('/[^a-z0-9-_]/', '-', strtolower($name));
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
        return trim($text, '-');
    }
}
