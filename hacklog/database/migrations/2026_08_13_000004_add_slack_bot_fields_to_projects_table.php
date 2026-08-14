<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Stable Slack channel ID (e.g. C1234567890) used to resolve channel → project.
            // Channel names are not stable identifiers and must not be used for mapping.
            $table->string('slack_channel_id', 30)->nullable()->after('slack_webhook_url');

            // Whether the @hacklog bot should respond to mentions in the mapped channel.
            $table->boolean('slack_bot_enabled')->default(false)->after('slack_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['slack_channel_id', 'slack_bot_enabled']);
        });
    }
};
