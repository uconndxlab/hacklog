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
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('honeycrisp_project_id')->nullable()->after('slack_bot_enabled');
            $table->string('honeycrisp_project_name')->nullable()->after('honeycrisp_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['honeycrisp_project_id', 'honeycrisp_project_name']);
        });
    }
};
