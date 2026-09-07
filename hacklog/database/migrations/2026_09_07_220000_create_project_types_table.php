<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 80)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        DB::table('project_types')->insert([
            ['key' => 'website', 'name' => 'Website', 'position' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'webapp', 'name' => 'Webapp', 'position' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'graphic_design', 'name' => 'Graphic design', 'position' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'program', 'name' => 'Program', 'position' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'other', 'name' => 'Other', 'position' => 50, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_types');
    }
};
