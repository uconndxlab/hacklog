<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates user role enum to support visibility-based access:
     * - admin: Full access to all projects
     * - team: Internal staff, see all projects by default
     * - client: External users, see only explicitly shared projects
     * 
     * Existing 'user' role is migrated to 'team' to maintain current behavior.
     */
    public function up(): void
    {
        // SQLite-specific approach: We need to handle the case where
        // the schema may have been partially migrated
        
        // Temporarily disable foreign key constraints
        DB::statement('PRAGMA foreign_keys = OFF;');
        
        // Create a new table with the correct schema
        Schema::create('users_new', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'team', 'client'])->default('team');
            $table->boolean('active')->default(true);
            $table->string('netid')->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        
        // Copy data from old table, converting 'user' role to 'team'
        DB::statement("
            INSERT INTO users_new (id, name, email, email_verified_at, password, role, active, netid, remember_token, created_at, updated_at)
            SELECT id, name, email, email_verified_at, password, 
                   CASE WHEN role = 'user' THEN 'team' ELSE role END,
                   active, netid, remember_token, created_at, updated_at
            FROM users
        ");
        
        // Drop old table and rename new one
        Schema::dropIfExists('users');
        Schema::rename('users_new', 'users');
        
        // Re-enable foreign key constraints
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert team/client roles to 'user'
        DB::table('users')
            ->whereIn('role', ['team', 'client'])
            ->update(['role' => 'user']);
        
        // Restore original enum
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])
                ->default('user')
                ->change();
        });
    }
};
