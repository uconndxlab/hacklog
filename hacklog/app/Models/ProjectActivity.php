<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'user_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new activity log entry
     */
    public static function log(int $projectId, ?int $userId, string $action, ?array $metadata = null): void
    {
        static::create([
            'project_id' => $projectId,
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
        $projectName = $this->project ? $this->project->name : 'Unknown Project';
        
        return match($this->action) {
            'created' => sprintf('%s created project %s', $userName, $projectName),
            'updated' => sprintf('%s updated project %s', $userName, $projectName),
            'status_changed' => sprintf(
                '%s changed status of %s from %s to %s',
                $userName,
                $projectName,
                $this->metadata['from'] ?? 'unknown',
                $this->metadata['to'] ?? 'unknown'
            ),
            default => sprintf('%s: %s on %s', $userName, $this->action, $projectName),
        };
    }
}
