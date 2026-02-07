<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectResource extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'type',
        'url',
        'content',
        'position',
    ];

    protected $casts = [
        'type' => 'string',
        'position' => 'integer',
    ];

    /**
     * Boot the model to set default ordering scope
     */
    protected static function booted()
    {
        // Order resources by position within queries
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderBy('position', 'asc');
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the next position for a new resource in a project
     */
    public static function getNextPositionInProject(int $projectId): int
    {
        $maxPosition = static::withoutGlobalScope('ordered')
            ->where('project_id', $projectId)
            ->max('position');
        return $maxPosition !== null ? $maxPosition + 1 : 0;
    }

    /**
     * Move this resource up (swap with previous resource)
     */
    public function moveUp(): bool
    {
        if ($this->position === null) {
            return false;
        }

        $previousResource = static::where('project_id', $this->project_id)
            ->where('position', '<', $this->position)
            ->orderBy('position', 'desc')
            ->first();

        if (!$previousResource) {
            return false; // Already at top
        }

        // Swap positions
        $tempPosition = $this->position;
        $this->position = $previousResource->position;
        $previousResource->position = $tempPosition;

        $this->save();
        $previousResource->save();

        return true;
    }

    /**
     * Move this resource down (swap with next resource)
     */
    public function moveDown(): bool
    {
        if ($this->position === null) {
            return false;
        }

        $nextResource = static::where('project_id', $this->project_id)
            ->where('position', '>', $this->position)
            ->orderBy('position', 'asc')
            ->first();

        if (!$nextResource) {
            return false; // Already at bottom
        }

        // Swap positions
        $tempPosition = $this->position;
        $this->position = $nextResource->position;
        $nextResource->position = $tempPosition;

        $this->save();
        $nextResource->save();

        return true;
    }

    /**
     * Check if this resource can move up
     */
    public function canMoveUp(): bool
    {
        if ($this->position === null) {
            return false;
        }

        return static::where('project_id', $this->project_id)
            ->where('position', '<', $this->position)
            ->exists();
    }

    /**
     * Check if this resource can move down
     */
    public function canMoveDown(): bool
    {
        if ($this->position === null) {
            return false;
        }

        return static::where('project_id', $this->project_id)
            ->where('position', '>', $this->position)
            ->exists();
    }

    /**
     * Get a short excerpt from the content for display
     */
    public function getExcerpt(int $length = 100): ?string
    {
        if (!$this->content) {
            return null;
        }

        // Strip HTML tags and get plain text
        $plainText = strip_tags($this->content);
        
        if (mb_strlen($plainText) <= $length) {
            return $plainText;
        }

        return mb_substr($plainText, 0, $length) . '...';
    }
}
