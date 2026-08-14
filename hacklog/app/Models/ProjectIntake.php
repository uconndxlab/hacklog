<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectIntake extends Model
{
    const STATUS_QUEUED     = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY      = 'ready';
    const STATUS_FAILED     = 'failed';

    const STATUS_VALUES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_READY,
        self::STATUS_FAILED,
    ];

    const SOURCE_TYPE_MANUAL = 'manual';
    const SOURCE_TYPE_SLACK  = 'slack';

    protected $fillable = [
        'project_id',
        'user_id',
        'source_type',
        'source_content',
        'status',
        'provider',
        'model',
        'ollama_summary',
        'error_message',
        'processing_started_at',
        'processing_completed_at',
        'correlation_id',
        'slack_context',
    ];

    protected $casts = [
        'processing_started_at'   => 'datetime',
        'processing_completed_at' => 'datetime',
        'slack_context'           => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProjectIntakeProposal::class);
    }

    // -------------------------------------------------------------------------
    // Accessors / helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this intake has reached a terminal status (ready or failed).
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_FAILED], true);
    }

    /**
     * A condensed single-line preview of the source content for list views.
     */
    public function sourcePreview(int $maxLength = 100): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($this->source_content));

        return mb_strimwidth((string) $normalized, 0, $maxLength, '…');
    }
}
