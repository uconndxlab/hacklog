<?php

namespace App\Console\Commands;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateSampleTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:generate-sample {--project= : Project ID} {--epic= : Epic ID} {--count=5 : Number of tasks to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sample tasks for a given epic with random assignments and future due dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get or prompt for project
        $project = $this->getProject();

        // Get or prompt for epic
        $epic = $this->getEpic($project);

        // Get number of tasks
        $count = $this->getTaskCount();

        // Generate tasks
        $this->generateTasks($project, $epic, $count);

        $this->info("Successfully generated {$count} sample tasks for epic '{$epic->name}'!");
        return Command::SUCCESS;
    }

    private function getProject(): Project
    {
        $projectId = $this->option('project');

        if ($projectId) {
            $project = Project::find($projectId);
            if (!$project) {
                $this->error("Project with ID {$projectId} not found.");
                return Command::FAILURE;
            }
            return $project;
        }

        $projects = Project::orderBy('name')->get();

        if ($projects->isEmpty()) {
            $this->error('No projects found. Please create a project first.');
            return Command::FAILURE;
        }

        $options = [];
        foreach ($projects as $project) {
            $options[(string)$project->id] = "{$project->name} (ID: {$project->id})";
        }

        $projectId = $this->choice('Select a project', $options);
        
        // Extract ID from the selected option (format: "Name (ID: X)")
        if (preg_match('/\(ID: (\d+)\)$/', $projectId, $matches)) {
            $projectId = $matches[1];
        }

        return $projects->find($projectId);
    }

    private function getEpic(Project $project): Epic
    {
        $epicId = $this->option('epic');

        if ($epicId) {
            $epic = $project->epics()->find($epicId);
            if (!$epic) {
                $this->error("Epic with ID {$epicId} not found in project '{$project->name}'.");
                return Command::FAILURE;
            }
            return $epic;
        }

        $epics = $project->epics()->orderBy('name')->get();

        if ($epics->isEmpty()) {
            $this->error("No epics found in project '{$project->name}'. Please create an epic first.");
            return Command::FAILURE;
        }

        $options = [];
        foreach ($epics as $epic) {
            $options[(string)$epic->id] = "{$epic->name} (ID: {$epic->id})";
        }

        $epicId = $this->choice('Select an epic', $options);
        
        // Extract ID from the selected option (format: "Name (ID: X)")
        if (preg_match('/\(ID: (\d+)\)$/', $epicId, $matches)) {
            $epicId = $matches[1];
        }

        return $epics->find($epicId);
    }

    private function getTaskCount(): int
    {
        $count = $this->option('count');

        // Always prompt if no count provided or if count is default value
        if (!$count || $count == 5) {
            $count = (int) $this->ask('How many tasks would you like to generate?', 5);
        }

        return max(1, $count);
    }

    private function generateTasks(Project $project, Epic $epic, int $count): void
    {
        $users = User::where('active', true)->get();
        $columns = $project->columns()->orderBy('position')->get();

        if ($users->isEmpty()) {
            $this->error('No active users found. Please create users first.');
            return;
        }

        if ($columns->isEmpty()) {
            $this->error('No columns found for this project. Please create columns first.');
            return;
        }

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $taskTitles = [
            'Implement user authentication',
            'Create database schema',
            'Build API endpoints',
            'Design user interface',
            'Write unit tests',
            'Set up CI/CD pipeline',
            'Configure deployment',
            'Optimize performance',
            'Add error handling',
            'Create documentation',
            'Implement caching',
            'Add logging system',
            'Build admin dashboard',
            'Create user profiles',
            'Implement notifications',
            'Add search functionality',
            'Build reporting system',
            'Create backup strategy',
            'Implement security measures',
            'Add internationalization',
        ];

        $statuses = ['planned', 'active', 'completed'];
        $statusWeights = [0.4, 0.4, 0.2]; // 40% planned, 40% active, 20% completed

        for ($i = 0; $i < $count; $i++) {
            // Get random title
            $title = $taskTitles[array_rand($taskTitles)] . ' ' . ($i + 1);

            // Generate description
            $description = $this->generateDescription();

            // Get random status with weights
            $status = $this->weightedRandomChoice($statuses, $statusWeights);

            // Get default column (first column or one marked as default)
            $defaultColumn = $columns->first(fn($col) => $col->is_default) ?? $columns->first();

            // Generate position
            $position = Task::getNextPositionInColumn($defaultColumn->id);

            // Generate dates within epic bounds
            $dates = $this->generateDatesWithinEpic($epic);

            // Create task
            $task = Task::create([
                'epic_id' => $epic->id,
                'column_id' => $defaultColumn->id,
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'position' => $position,
                'start_date' => $dates['start_date'],
                'due_date' => $dates['due_date'],
            ]);

            // Assign random users (1-3 users per task)
            $assignedUsers = $users->random(min(rand(1, 3), $users->count()));
            $task->users()->attach($assignedUsers->pluck('id'));

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    private function generateDescription(): string
    {
        $descriptions = [
            'This task involves implementing the core functionality required for the feature.',
            'Need to ensure proper validation and error handling throughout the implementation.',
            'Focus on creating a clean, maintainable solution that follows best practices.',
            'Coordinate with the design team to ensure the implementation matches the requirements.',
            'Test thoroughly to ensure compatibility across different browsers and devices.',
            'Document the implementation details for future reference and maintenance.',
            'Consider performance implications and optimize where necessary.',
            'Ensure security best practices are followed throughout the implementation.',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function generateDatesWithinEpic(Epic $epic): array
    {
        // Only generate dates if the epic has date bounds
        if (!$epic->start_date && !$epic->end_date) {
            return [
                'start_date' => null,
                'due_date' => null,
            ];
        }

        $startDate = $epic->start_date ?? now();
        $endDate = $epic->end_date ?? now()->addDays(90);

        // Ensure we have valid date bounds
        if ($startDate->gte($endDate)) {
            $endDate = $startDate->copy()->addDays(30);
        }

        $epicDuration = $endDate->diffInDays($startDate);

        // Generate random start date within first 80% of epic duration
        $maxStartOffset = max(0, intval($epicDuration * 0.8));
        $startOffset = rand(0, $maxStartOffset);
        $taskStart = $startDate->copy()->addDays($startOffset);

        // Generate due date within remaining epic time
        $remainingDays = $endDate->diffInDays($taskStart);
        
        if ($remainingDays <= 1) {
            // Very little time left, due date is epic end
            $taskEnd = $endDate->copy();
        } else {
            // Random due date between task start and epic end
            $maxTaskDuration = min(14, $remainingDays); // Max 2 weeks or remaining time
            $minTaskDuration = min(1, $remainingDays);
            $taskDuration = rand($minTaskDuration, max($minTaskDuration, $maxTaskDuration));
            $taskEnd = $taskStart->copy()->addDays($taskDuration);
            
            // Ensure task doesn't go beyond epic end
            if ($taskEnd->gt($endDate)) {
                $taskEnd = $endDate->copy();
            }
        }

        return [
            'start_date' => rand(0, 1) ? $taskStart : null, // 50% chance of having a start date
            'due_date' => $taskEnd,
        ];
    }

    private function weightedRandomChoice(array $options, array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand() / mt_getrandmax() * $totalWeight;

        $cumulativeWeight = 0;
        foreach ($options as $index => $option) {
            $cumulativeWeight += $weights[$index];
            if ($random <= $cumulativeWeight) {
                return $option;
            }
        }

        return $options[0]; // fallback
    }
}