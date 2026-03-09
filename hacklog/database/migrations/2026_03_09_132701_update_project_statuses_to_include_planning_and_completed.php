<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to drop and recreate the table with new enum
        // For MySQL/PostgreSQL, we can alter the column directly
        
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support modifying enums, so we need to recreate the table
            DB::statement('PRAGMA foreign_keys=off');
            
            // Create temporary table with new schema
            DB::statement("
                CREATE TABLE projects_temp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR NOT NULL,
                    description TEXT,
                    status VARCHAR NOT NULL DEFAULT 'active' CHECK(status IN ('planning', 'active', 'on_hold', 'completed', 'archived')),
                    staffing_model VARCHAR NOT NULL DEFAULT 'dedicated' CHECK(staffing_model IN ('dedicated', 'shared')),
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ");
            
            // Copy data, converting 'paused' to 'on_hold'
            DB::statement("
                INSERT INTO projects_temp (id, name, description, status, staffing_model, created_at, updated_at)
                SELECT id, name, description, 
                    CASE WHEN status = 'paused' THEN 'on_hold' ELSE status END,
                    staffing_model, created_at, updated_at
                FROM projects
            ");
            
            // Drop old table and rename temp
            DB::statement('DROP TABLE projects');
            DB::statement('ALTER TABLE projects_temp RENAME TO projects');
            
            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // MySQL/PostgreSQL: First update the data, then alter the column
            DB::table('projects')
                ->where('status', 'paused')
                ->update(['status' => 'on_hold']);
            
            DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('planning', 'active', 'on_hold', 'completed', 'archived') NOT NULL DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite: Recreate table with old schema
            DB::statement('PRAGMA foreign_keys=off');
            
            DB::statement("
                CREATE TABLE projects_temp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR NOT NULL,
                    description TEXT,
                    status VARCHAR NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'paused', 'archived')),
                    staffing_model VARCHAR NOT NULL DEFAULT 'dedicated' CHECK(staffing_model IN ('dedicated', 'shared')),
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ");
            
            // Copy data, converting back and setting planning/completed to active
            DB::statement("
                INSERT INTO projects_temp (id, name, description, status, staffing_model, created_at, updated_at)
                SELECT id, name, description,
                    CASE 
                        WHEN status = 'on_hold' THEN 'paused'
                        WHEN status IN ('planning', 'completed') THEN 'active'
                        ELSE status
                    END,
                    staffing_model, created_at, updated_at
                FROM projects
            ");
            
            DB::statement('DROP TABLE projects');
            DB::statement('ALTER TABLE projects_temp RENAME TO projects');
            
            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // MySQL/PostgreSQL
            DB::table('projects')
                ->where('status', 'on_hold')
                ->update(['status' => 'paused']);
            
            DB::table('projects')
                ->whereIn('status', ['planning', 'completed'])
                ->update(['status' => 'active']);
            
            DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('active', 'paused', 'archived') NOT NULL DEFAULT 'active'");
        }
    }
};
