<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'position',
        'is_default',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * Relationships to touch when this column or its tasks are updated.
     * This will update the project's updated_at timestamp.
     */
    protected $touches = ['project'];

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderBy('position');
        });
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
