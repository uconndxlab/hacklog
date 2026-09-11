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
            $table->unsignedBigInteger('honeycrisp_billed_total_cents')->nullable()->after('honeycrisp_project_name');
            $table->timestamp('honeycrisp_billed_fetched_at')->nullable()->after('honeycrisp_billed_total_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['honeycrisp_billed_total_cents', 'honeycrisp_billed_fetched_at']);
        });
    }
};
