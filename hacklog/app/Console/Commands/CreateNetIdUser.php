<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LdapService;
use Illuminate\Console\Command;

class CreateNetIdUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create 
                           {netid : The NetID of the user to create}
                           {--role=user : The role for the user (user or admin)}
                           {--inactive : Create the user as inactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user by NetID with LDAP lookup';

    protected LdapService $ldapService;

    public function __construct(LdapService $ldapService)
    {
        parent::__construct();
        $this->ldapService = $ldapService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $netid = strtolower(trim($this->argument('netid')));
        $role = $this->option('role');
        $active = !$this->option('inactive');

        // Validate role
        if (!in_array($role, ['user', 'admin'])) {
            $this->error('Role must be either "user" or "admin"');
            return 1;
        }

        // Check if user already exists
        if (User::where('netid', $netid)->exists()) {
            $this->error("User with NetID '{$netid}' already exists!");
            return 1;
        }

        $this->info("Looking up NetID '{$netid}' in directory...");

        // Look up user in LDAP
        $ldapData = $this->ldapService->lookupUser($netid);

        if (!$ldapData) {
            $this->error("NetID '{$netid}' not found in directory.");
            $this->line('Please verify the NetID is correct.');
            
            // Ask if they want to continue anyway
            if ($this->confirm('Do you want to create the user anyway with minimal info?')) {
                $ldapData = [
                    'name' => $netid,
                    'email' => $netid . '@uconn.edu'
                ];
                $this->warn('Creating user with fallback name and email.');
            } else {
                $this->info('User creation cancelled.');
                return 1;
            }
        } else {
            $this->info('✓ Found user in directory:');
            $this->line("  Name: {$ldapData['name']}");
            $this->line("  Email: {$ldapData['email']}");
        }

        // Show what will be created
        $this->info('Creating user with the following details:');
        $this->table(
            ['Field', 'Value'],
            [
                ['NetID', $netid],
                ['Name', $ldapData['name']],
                ['Email', $ldapData['email']],
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
                'netid' => $netid,
                'name' => $ldapData['name'],
                'email' => $ldapData['email'],
                'role' => $role,
                'active' => $active,
                'password' => '', // Not used for CAS auth
            ]);

            $this->info('✓ User created successfully!');
            $this->line("User ID: {$user->id}");
            
            if ($role === 'admin') {
                $this->warn('⚠ This user has admin privileges and can manage other users.');
            }
            
            if (!$active) {
                $this->warn('⚠ This user is inactive and cannot log in until activated.');
            } else {
                $this->info('The user can now log in using their NetID via CAS.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create user: ' . $e->getMessage());
            return 1;
        }
    }
}
