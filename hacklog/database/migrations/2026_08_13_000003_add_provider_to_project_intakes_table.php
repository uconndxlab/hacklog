<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_intakes', function (Blueprint $table) {
            // Track which AI provider processed this intake (ollama, openai, …)
            $table->string('provider', 30)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('project_intakes', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
