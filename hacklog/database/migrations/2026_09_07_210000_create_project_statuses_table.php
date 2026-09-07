<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 80);
            $table->string('color', 7)->default('#6c757d');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('show_in_active_views')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        DB::table('project_statuses')->insert([
            ['key' => 'planning', 'name' => 'Planning', 'color' => '#0dcaf0', 'position' => 10, 'show_in_active_views' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'active', 'name' => 'Active', 'color' => '#198754', 'position' => 20, 'show_in_active_views' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'on_hold', 'name' => 'On Hold', 'color' => '#ffc107', 'position' => 30, 'show_in_active_views' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'completed', 'name' => 'Completed', 'color' => '#6482b4', 'position' => 40, 'show_in_active_views' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'archived', 'name' => 'Archived', 'color' => '#6c757d', 'position' => 50, 'show_in_active_views' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 80)->default('active')->change();
        });
    }

    public function down(): void
    {
        DB::table('projects')
            ->whereNotIn('status', ['planning', 'active', 'on_hold', 'completed', 'archived'])
            ->update(['status' => 'active']);

        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'archived'])
                ->default('active')
                ->change();
        });

        Schema::dropIfExists('project_statuses');
    }
};
