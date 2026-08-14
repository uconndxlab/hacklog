<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Source metadata — preserved for traceability and future sources (email, Slack, etc.)
            $table->string('source_type', 50)->default('manual');
            $table->text('source_content');

            // Processing lifecycle
            $table->string('status', 20)->default('queued')->index();
            $table->string('model', 100)->nullable();
            $table->text('ollama_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();

            // Log correlation — links queue job log entries to this intake
            $table->string('correlation_id', 36)->nullable();

            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_intakes');
    }
};
