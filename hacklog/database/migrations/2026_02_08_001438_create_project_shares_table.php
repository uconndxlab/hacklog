<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates project_shares table for explicit visibility grants.
     * 
     * Projects can be shared with:
     * - Individual users (shareable_type = 'user', shareable_id = user.id)
     * - User roles (shareable_type = 'role', shareable_id = role name string)
     * 
     * This is additive: Team/Admin users see all projects by default,
     * Client users only see projects shared with them.
     */
    public function up(): void
    {
        Schema::create('project_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            
            // Polymorphic sharing: can share with users or roles
            $table->string('shareable_type'); // 'user' or 'role'
            $table->string('shareable_id');   // user.id or role name ('client', 'team')
            
            $table->timestamps();
            
            // Prevent duplicate shares
            $table->unique(['project_id', 'shareable_type', 'shareable_id']);
            
            // Index for efficient visibility queries
            $table->index(['shareable_type', 'shareable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_shares');
    }
};
