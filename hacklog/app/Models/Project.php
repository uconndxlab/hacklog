<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class);
    }
}
