<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateLocalUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-local 
                           {email : The email address for the user}
                           {name : The full name of the user}
                           {--password= : The password for the user (will prompt if not provided)}
                           {--role=user : The role for the user (user or admin)}
                           {--inactive : Create the user as inactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new local user with email/password authentication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = strtolower(trim($this->argument('email')));
        $name = trim($this->argument('name'));
        $role = $this->option('role');
        $active = !$this->option('inactive');
        $password = $this->option('password');

        // Validate email
        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email']
        );

        if ($validator->fails()) {
            $this->error('Invalid email address format.');
            return 1;
        }

        // Validate role
        if (!in_array($role, ['user', 'admin'])) {
            $this->error('Role must be either "user" or "admin"');
            return 1;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists!");
            return 1;
        }

        // Get password if not provided
        if (!$password) {
            $password = $this->secret('Enter password for the user');
            $passwordConfirm = $this->secret('Confirm password');

            if ($password !== $passwordConfirm) {
                $this->error('Passwords do not match!');
                return 1;
            }
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return 1;
        }

        // Show what will be created
        $this->info('Creating user with the following details:');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $name],
                ['Email', $email],
                ['Role', $role],
                ['Active', $active ? 'Yes' : 'No'],
            ]
        );

        // Confirm creation
        if (!$this->confirm('Create this user?', true)) {
            $this->info('User creation cancelled.');
            return 1;
        }

        try {
            // Create the user
            $user = User::create([
                'netid' => null,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'active' => $active,
                'password' => Hash::make($password),
            ]);

            $this->info('✓ User created successfully!');
            $this->line("User ID: {$user->id}");
            
            if ($role === 'admin') {
                $this->warn('⚠ This user has admin privileges and can manage other users.');
            }
            
            if (!$active) {
                $this->warn('⚠ This user is inactive and cannot log in until activated.');
            } else {
                $this->info('The user can now log in using their email and password.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create user: ' . $e->getMessage());
            return 1;
        }
    }
}
