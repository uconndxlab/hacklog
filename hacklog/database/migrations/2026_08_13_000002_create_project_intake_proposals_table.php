<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_intake_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_intake_id')->constrained()->onDelete('cascade');

            // Proposed task fields — mirrors Task model fillable fields
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('suggested_phase_id')->nullable()->constrained('phases')->onDelete('set null');
            $table->foreignId('suggested_assignee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('due_date')->nullable();

            // AI metadata
            $table->decimal('confidence', 4, 3)->nullable();   // 0.000 – 1.000
            $table->text('source_excerpt')->nullable();
            $table->string('possible_duplicate_of')->nullable(); // stored as text; no FK (semantic, not relational)

            // Human disposition — never deleted; retained for future duplicate-awareness
            $table->string('status', 20)->default('pending');          // pending | approved | dismissed
            $table->string('disposition_reason', 50)->nullable();      // not_actionable | already_handled | duplicate | not_needed | null
            $table->foreignId('created_task_id')->nullable()->constrained('tasks')->onDelete('set null');

            $table->timestamps();

            $table->index(['project_intake_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_intake_proposals');
    }
};
