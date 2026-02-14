<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Converts tasks.status from ENUM to string type.
     * This is necessary for SQLite compatibility and future extensibility.
     * 
     * Current status values: planned, active, completed
     * Future: awaiting_feedback
     * 
     * Strategy:
     * 1. Add temporary status_new column as string
     * 2. Copy data from status to status_new
     * 3. Drop old status column
     * 4. Rename status_new to status
     */
    public function up(): void
    {
        // Step 1: Add temporary column
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status_new')->nullable()->after('description');
        });

        // Step 2: Copy existing data
        DB::statement('UPDATE tasks SET status_new = status');

        // Step 3: Drop old ENUM column
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // Step 4: Rename status_new to status and set defaults
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        // Step 5: Make column not nullable with default
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default('planned')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Note: Reverting back to ENUM will fail on SQLite.
     * This is intentional - the forward migration is the correct architecture.
     */
    public function down(): void
    {
        // For MySQL/MariaDB: convert back to ENUM
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('status_temp')->nullable()->after('description');
            });

            DB::statement('UPDATE tasks SET status_temp = status');

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('status');
            });

            // Recreate as ENUM (only works on MySQL/MariaDB)
            DB::statement("ALTER TABLE tasks ADD COLUMN status ENUM('planned', 'active', 'completed') DEFAULT 'planned' NOT NULL AFTER description");
            
            DB::statement('UPDATE tasks SET status = status_temp');

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('status_temp');
            });
        } else {
            // SQLite: just convert to string with validation in application layer
            // Cannot use ENUM in SQLite
            throw new \Exception('Cannot rollback to ENUM type on SQLite. Keep string type and validate in application.');
        }
    }
};
