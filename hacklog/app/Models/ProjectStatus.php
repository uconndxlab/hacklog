<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProjectStatus extends Model
{
    protected $fillable = [
        'key',
        'name',
        'color',
        'position',
        'show_in_active_views',
    ];

    protected $casts = [
        'position' => 'integer',
        'show_in_active_views' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'status', 'key');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public static function definitions(): Collection
    {
        if (! Schema::hasTable('project_statuses')) {
            return collect(Project::DEFAULT_STATUS_LABELS)->map(function ($name, $key) {
                return new static([
                    'key' => $key,
                    'name' => $name,
                    'color' => Project::DEFAULT_STATUS_COLORS[$key],
                    'show_in_active_views' => in_array($key, [Project::STATUS_PLANNING, Project::STATUS_ACTIVE], true),
                ]);
            })->values();
        }

        return static::query()->ordered()->get();
    }

    public function textColor(): string
    {
        $hex = ltrim($this->color, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 165 ? '#212529' : '#ffffff';
    }
}
