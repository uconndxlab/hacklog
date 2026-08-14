<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_intakes', function (Blueprint $table) {
            // Stores Slack provenance for intakes originated from Slack bot mentions.
            // Keys: channel_id, thread_ts, message_ts, user_id, event_id.
            // Null for non-Slack intakes.
            $table->json('slack_context')->nullable()->after('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_intakes', function (Blueprint $table) {
            $table->dropColumn('slack_context');
        });
    }
};
