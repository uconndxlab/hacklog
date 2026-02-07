<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $fillable = [
        'epic_id',
        'column_id',
        'title',
        'description',
        'status',
        'position',
        'start_date',
        'due_date',
    ];

    protected $casts = [
        'status' => 'string',
        'position' => 'integer',
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Determine if the task is overdue.
     * 
     * A task is overdue if:
     * - due_date exists
     * - AND due_date < today
     * - AND status != completed
     */
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        if ($this->status === 'completed') {
            return false;
        }

        return $this->due_date->isBefore(today());
    }

    /**
     * Boot the model to set default ordering scope
     */
    protected static function booted()
    {
        // Order tasks by position within queries (nulls last)
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderByRaw('position IS NULL, position ASC');
        });
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the next position for a new task in a column
     */
    public static function getNextPositionInColumn(int $columnId): int
    {
        $maxPosition = static::withoutGlobalScope('ordered')
            ->where('column_id', $columnId)
            ->max('position');
        return $maxPosition !== null ? $maxPosition + 1 : 0;
    }

    /**
     * Move this task up (swap with previous task)
     */
    public function moveUp(): bool
    {
        if ($this->position === null) {
            return false; // Task has no position
        }

        $previousTask = static::where('column_id', $this->column_id)
            ->where('position', '<', $this->position)
            ->orderBy('position', 'desc')
            ->first();

        if (!$previousTask) {
            return false; // Already at top
        }

        // Swap positions
        $tempPosition = $this->position;
        $this->position = $previousTask->position;
        $previousTask->position = $tempPosition;

        $this->save();
        $previousTask->save();

        return true;
    }

    /**
     * Move this task down (swap with next task)
     */
    public function moveDown(): bool
    {
        if ($this->position === null) {
            return false; // Task has no position
        }

        $nextTask = static::where('column_id', $this->column_id)
            ->where('position', '>', $this->position)
            ->orderBy('position', 'asc')
            ->first();

        if (!$nextTask) {
            return false; // Already at bottom
        }

        // Swap positions
        $tempPosition = $this->position;
        $this->position = $nextTask->position;
        $nextTask->position = $tempPosition;

        $this->save();
        $nextTask->save();

        return true;
    }

    /**
     * Check if this task can move up
     */
    public function canMoveUp(): bool
    {
        if ($this->position === null) {
            return false;
        }

        return static::where('column_id', $this->column_id)
            ->where('position', '<', $this->position)
            ->exists();
    }

    /**
     * Check if this task can move down
     */
    public function canMoveDown(): bool
    {
        if ($this->position === null) {
            return false;
        }

        return static::where('column_id', $this->column_id)
            ->where('position', '>', $this->position)
            ->exists();
    }
}
