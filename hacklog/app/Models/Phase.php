<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phase extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'status' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Determine if the phase is overdue.
     * 
     * A phase is overdue if:
     * - end_date exists
     * - AND end_date < today
     * - AND status != completed
     */
    public function isOverdue(): bool
    {
        if (!$this->end_date) {
            return false;
        }

        if ($this->status === 'completed') {
            return false;
        }

        return $this->end_date->isBefore(today());
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
