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
        // First, update existing 'user' role to 'team'
        DB::table('users')
            ->where('role', 'user')
            ->update(['role' => 'team']);
        
        // Now alter the column to support new roles
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'team', 'client'])
                ->default('team')
                ->change();
        });
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
