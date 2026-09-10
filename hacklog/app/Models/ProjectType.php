<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProjectType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_type', 'key');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public static function definitions(): Collection
    {
        if (! Schema::hasTable('project_types')) {
            return collect(Project::DEFAULT_TYPE_LABELS)->map(function ($name, $key) {
                return new static(['key' => $key, 'name' => $name]);
            })->values();
        }

        return static::query()->ordered()->get();
    }
}
