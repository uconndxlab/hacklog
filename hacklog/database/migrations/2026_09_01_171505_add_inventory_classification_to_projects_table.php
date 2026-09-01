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
            $table->string('project_type')->nullable()->after('staffing_model');
            $table->foreignId('department_id')->nullable()->after('project_type')->constrained('departments')->nullOnDelete();
            $table->foreignId('nested_department_id')->nullable()->after('department_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('major_office_id')->nullable()->after('nested_department_id')->constrained('major_offices')->nullOnDelete();
            $table->string('client_pi')->nullable()->after('major_office_id');
            $table->string('client_category')->nullable()->after('client_pi');
            $table->string('uconn_affiliation')->nullable()->after('client_category');
            $table->decimal('grant_value', 12, 2)->nullable()->after('uconn_affiliation');
            $table->string('sponsor')->nullable()->after('grant_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('nested_department_id');
            $table->dropConstrainedForeignId('major_office_id');
            $table->dropColumn([
                'project_type',
                'client_pi',
                'client_category',
                'uconn_affiliation',
                'grant_value',
                'sponsor',
            ]);
        });
    }
};
