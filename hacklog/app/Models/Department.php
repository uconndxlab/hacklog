<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
    ];

    protected $casts = [
        'name' => 'string',
        'parent_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('name');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'department_id');
    }

    public function nestedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'nested_department_id');
    }

    public function isHomeDepartment(): bool
    {
        return $this->parent_id === null;
    }

    public function scopeHome(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeNested(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }
}
