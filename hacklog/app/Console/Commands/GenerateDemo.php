<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Epic;
use App\Models\Task;
use App\Models\User;
use App\Models\Column;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hacklog:generate-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate realistic demo data for local development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Hacklog Demo Data Generator');
        $this->newLine();

        // Prompt for number of projects
        $projectCount = $this->ask('How many projects would you like to create?', 3);
        
        if (!is_numeric($projectCount) || $projectCount < 1) {
            $this->error('Please enter a valid number greater than 0.');
            return 1;
        }

        $projectCount = (int) $projectCount;

        // Check if users exist for assignment
        $users = User::where('active', true)->get();
        $hasUsers = $users->isNotEmpty();

        if (!$hasUsers) {
            $this->warn('No active users found. Tasks will be created unassigned.');
        } else {
            $this->info("Found {$users->count()} active users for task assignments.");
        }

        $this->newLine();
        $this->info("Generating {$projectCount} projects with epics and tasks...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($projectCount);
        $progressBar->start();

        $totalProjects = 0;
        $totalEpics = 0;
        $totalTasks = 0;

        for ($i = 1; $i <= $projectCount; $i++) {
            // Create project with realistic created_at in the past 30-60 days
            $project = Project::create([
                'name' => "Demo Project {$i}",
                'description' => "This is a demo project created for local development and testing.",
                'status' => 'active',
                'created_at' => Carbon::now()->subDays(rand(30, 60)),
            ]);
            $totalProjects++;

            // Ensure project has columns (use default structure)
            $this->ensureProjectColumns($project);

            // Create 2 epics per project
            for ($epicNum = 1; $epicNum <= 2; $epicNum++) {
                $epic = $this->createEpic($project, $epicNum);
                $totalEpics++;

                // Create 5 tasks per epic
                $tasksCreated = $this->createTasksForEpic($project, $epic, $users, $hasUsers);
                $totalTasks += $tasksCreated;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✓ Demo data generated successfully!');
        $this->newLine();
        $this->line("  Projects created: {$totalProjects}");
        $this->line("  Epics created: {$totalEpics}");
        $this->line("  Tasks created: {$totalTasks}");
        $this->newLine();

        return 0;
    }

    /**
     * Ensure project has the standard column structure.
     */
    protected function ensureProjectColumns(Project $project): void
    {
        // Check if project already has columns
        if ($project->columns()->count() > 0) {
            return;
        }

        // Create default columns
        $defaultColumns = [
            ['name' => 'Backlog', 'position' => 1],
            ['name' => 'To Do', 'position' => 2],
            ['name' => 'In Progress', 'position' => 3],
            ['name' => 'Done', 'position' => 4],
        ];

        foreach ($defaultColumns as $columnData) {
            Column::create([
                'project_id' => $project->id,
                'name' => $columnData['name'],
                'position' => $columnData['position'],
            ]);
        }
    }

    /**
     * Create an epic with realistic date range (4-8 weeks).
     */
    protected function createEpic(Project $project, int $epicNumber): Epic
    {
        // Calculate epic start date
        // First epic starts in ~1-2 weeks
        // Second epic starts in ~5-6 weeks (slight overlap with first)
        $weeksOffset = $epicNumber === 1 ? rand(1, 2) : rand(5, 6);
        $startDate = Carbon::now()->addWeeks($weeksOffset)->addDays(rand(-3, 3));

        // Duration: 4-8 weeks
        $durationWeeks = rand(4, 8);
        $endDate = $startDate->copy()->addWeeks($durationWeeks)->addDays(rand(-2, 2));

        return Epic::create([
            'project_id' => $project->id,
            'name' => "Epic {$epicNumber}: Feature Development",
            'description' => "Demo epic with planned work spanning {$durationWeeks} weeks.",
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Create 5 tasks for an epic with realistic due date distribution.
     * 
     * Date distribution strategy:
     * - Divide epic duration into 5 roughly equal segments
     * - Place one task due date in each segment
     * - Add randomness (±2 days) to avoid perfect alignment
     * - Mix statuses: some open, some in_progress, some done
     */
    protected function createTasksForEpic(Project $project, Epic $epic, $users, bool $hasUsers): int
    {
        $columns = $project->columns()->orderBy('position')->get();
        
        if ($columns->isEmpty()) {
            return 0;
        }

        // Calculate epic duration in days
        $epicStart = $epic->start_date;
        $epicEnd = $epic->end_date;
        $epicDurationDays = $epicStart->diffInDays($epicEnd);

        // Create 5 tasks with staggered due dates
        $taskStatuses = ['planned', 'planned', 'active', 'completed', 'completed'];
        shuffle($taskStatuses); // Randomize status order

        for ($taskNum = 1; $taskNum <= 5; $taskNum++) {
            // Calculate due date: divide epic into 5 segments
            $segmentSize = $epicDurationDays / 5;
            $segmentStart = ($taskNum - 1) * $segmentSize;
            $baseDueDate = $epicStart->copy()->addDays($segmentStart + ($segmentSize / 2));
            
            // Add randomness: ±2 days
            $dueDate = $baseDueDate->addDays(rand(-2, 2));
            
            // Ensure due date stays within epic bounds
            if ($dueDate->lt($epicStart)) {
                $dueDate = $epicStart->copy()->addDays(rand(1, 3));
            }
            if ($dueDate->gt($epicEnd)) {
                $dueDate = $epicEnd->copy()->subDays(rand(1, 3));
            }

            $status = $taskStatuses[$taskNum - 1];

            // Determine column based on status
            $column = match($status) {
                'planned' => $columns->firstWhere('name', 'To Do') ?? $columns->first(),
                'active' => $columns->firstWhere('name', 'In Progress') ?? $columns->skip(2)->first(),
                'completed' => $columns->firstWhere('name', 'Done') ?? $columns->last(),
                default => $columns->first(),
            };

            $task = Task::create([
                'epic_id' => $epic->id,
                'column_id' => $column->id,
                'title' => "Task {$taskNum}: Implementation work",
                'description' => "Demo task with status: {$status}",
                'status' => $status,
                'due_date' => $dueDate,
                'position' => $taskNum,
            ]);

            // Assign to random user(s) if users exist
            if ($hasUsers && rand(1, 100) > 20) { // 80% chance of assignment
                $assignedUserCount = rand(1, min(2, $users->count())); // 1-2 users
                $assignedUsers = $users->random($assignedUserCount);
                
                foreach ($assignedUsers as $user) {
                    $task->users()->attach($user->id);
                }
            }
        }

        return 5;
    }
}
