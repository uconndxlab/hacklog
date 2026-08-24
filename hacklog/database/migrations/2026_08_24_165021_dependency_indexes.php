<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexNames = collect(Schema::getIndexes('task_dependencies'))->pluck('name');

        if (! $indexNames->contains('task_dependencies_task_id_dependency_id_unique')) {
            Schema::table('task_dependencies', function (Blueprint $table) {
                $table->unique(['task_id', 'dependency_id']);
            });
        }

        if (! $indexNames->contains('task_dependencies_dependency_id_index')) {
            Schema::table('task_dependencies', function (Blueprint $table) {
                $table->index('dependency_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexNames = collect(Schema::getIndexes('task_dependencies'))->pluck('name');

        Schema::table('task_dependencies', function (Blueprint $table) use ($indexNames) {
            if ($indexNames->contains('task_dependencies_task_id_dependency_id_unique')) {
                $table->dropUnique(['task_id', 'dependency_id']);
            }

            if ($indexNames->contains('task_dependencies_dependency_id_index')) {
                $table->dropIndex(['dependency_id']);
            }
        });
    }
};
