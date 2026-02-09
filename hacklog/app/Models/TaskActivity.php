<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new activity log entry
     */
    public static function log(int $taskId, ?int $userId, string $action, ?array $metadata = null): void
    {
        static::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Get a human-readable summary of this activity
     */
    public function getSummary(): string
    {
        $userName = $this->user ? $this->user->name : 'System';
        
        return match($this->action) {
            'status_changed' => sprintf(
                '%s changed status from %s to %s',
                $userName,
                $this->metadata['from'] ?? 'unknown',
                $this->metadata['to'] ?? 'unknown'
            ),
            'completed' => sprintf('%s marked as completed', $userName),
            'reopened' => sprintf('%s reopened task', $userName),
            'phase_changed' => sprintf(
                '%s moved to phase: %s',
                $userName,
                $this->metadata['to_name'] ?? 'unknown'
            ),
            'assignees_changed' => sprintf(
                '%s updated assignees',
                $userName
            ),
            'due_date_changed' => sprintf(
                '%s changed due date to %s',
                $userName,
                $this->metadata['to'] ?? 'none'
            ),
            'column_changed' => sprintf(
                '%s moved to column: %s',
                $userName,
                $this->metadata['to_name'] ?? 'unknown'
            ),
            default => sprintf('%s: %s', $userName, $this->action),
        };
    }
}
