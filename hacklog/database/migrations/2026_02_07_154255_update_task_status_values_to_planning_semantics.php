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
        // For SQLite, we need to recreate the table with the new enum values
        // First, rename the old table
        Schema::rename('tasks', 'tasks_old');
        
        // Create new table with updated enum
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->onDelete('cascade');
            $table->foreignId('column_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['planned', 'active', 'completed'])->default('planned');
            $table->integer('position')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
        
        // Copy data with status mapping
        DB::statement("
            INSERT INTO tasks (id, phase_id, column_id, title, description, status, position, start_date, due_date, created_at, updated_at)
            SELECT 
                id, 
                phase_id, 
                column_id, 
                title, 
                description, 
                CASE status
                    WHEN 'open' THEN 'planned'
                    WHEN 'in_progress' THEN 'active'
                    WHEN 'done' THEN 'completed'
                END,
                position,
                start_date,
                due_date,
                created_at, 
                updated_at
            FROM tasks_old
        ");
        
        // Drop old table
        Schema::dropIfExists('tasks_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename current table
        Schema::rename('tasks', 'tasks_new');
        
        // Recreate old table structure
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->onDelete('cascade');
            $table->foreignId('column_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'done'])->default('open');
            $table->integer('position')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
        
        // Copy data back with reverse mapping
        DB::statement("
            INSERT INTO tasks (id, phase_id, column_id, title, description, status, position, start_date, due_date, created_at, updated_at)
            SELECT 
                id, 
                phase_id, 
                column_id, 
                title, 
                description, 
                CASE status
                    WHEN 'planned' THEN 'open'
                    WHEN 'active' THEN 'in_progress'
                    WHEN 'completed' THEN 'done'
                END,
                position,
                start_date,
                due_date,
                created_at, 
                updated_at
            FROM tasks_new
        ");
        
        // Drop new table
        Schema::dropIfExists('tasks_new');
    }
};