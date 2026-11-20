{{-- Slack integration fields shared by project create and edit --}}
<div class="mb-3">
    <label for="slack_webhook_url" class="form-label">Slack Webhook URL <span class="text-muted fw-normal">(optional)</span></label>
    <input
        type="url"
        class="form-control @error('slack_webhook_url') is-invalid @enderror"
        id="slack_webhook_url"
        name="slack_webhook_url"
        value="{{ old('slack_webhook_url', $project?->slack_webhook_url) }}"
        placeholder="https://hooks.slack.com/services/...">
    <div class="form-text">
        When set, task creations and updates post a summary to this webhook every 5 minutes.
    </div>
    @error('slack_webhook_url')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="slack_channel_id" class="form-label">Slack Channel ID <span class="text-muted fw-normal">(optional)</span></label>
    <input
        type="text"
        class="form-control @error('slack_channel_id') is-invalid @enderror"
        id="slack_channel_id"
        name="slack_channel_id"
        value="{{ old('slack_channel_id', $project?->slack_channel_id) }}"
        placeholder="C1234567890"
        maxlength="30">
    <div class="form-text">
        The stable Slack channel ID (e.g. <code>C1234567890</code>) — not the channel name.
        Used to route <code>@hacklog</code> bot mentions to this project.
        Find it in Slack: right-click the channel → <em>View channel details</em> → scroll to the bottom.
    </div>
    @error('slack_channel_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <div class="form-check">
        <input
            type="checkbox"
            class="form-check-input"
            id="slack_bot_enabled"
            name="slack_bot_enabled"
            value="1"
            {{ old('slack_bot_enabled', $project?->slack_bot_enabled) ? 'checked' : '' }}>
        <label class="form-check-label" for="slack_bot_enabled">
            Enable <code>@hacklog</code> bot for this project
        </label>
    </div>
    <div class="form-text ms-4">
        When enabled and a Slack Channel ID is set, <code>@hacklog</code> will respond to mentions in that channel.
        The global bot token and signing secret are configured in the server environment.
    </div>
</div>
