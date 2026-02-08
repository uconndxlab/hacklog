<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

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

    public function shares(): HasMany
    {
        return $this->hasMany(ProjectShare::class);
    }

    /**
     * Visibility scope: filters projects based on user's role and sharing.
     * 
     * Logic:
     * - Admin/Team users: see all projects (no filtering)
     * - Client users: only see projects explicitly shared with them or their role
     * 
     * This centralizes visibility enforcement so controllers don't need to
     * manually check permissions.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Admin and team members see everything by default
        if ($user->canSeeAllProjects()) {
            return $query;
        }

        // Client users only see projects shared with them
        return $query->whereHas('shares', function ($q) use ($user) {
            $q->where(function ($shareQuery) use ($user) {
                // Shared directly with this user
                $shareQuery->where(function ($userShare) use ($user) {
                    $userShare->where('shareable_type', 'user')
                              ->where('shareable_id', (string)$user->id);
                })
                // Or shared with their role
                ->orWhere(function ($roleShare) use ($user) {
                    $roleShare->where('shareable_type', 'role')
                              ->where('shareable_id', $user->role);
                });
            });
        });
    }

    /**
     * Check if a project is shared with a specific user.
     */
    public function isSharedWith(User $user): bool
    {
        // Team/admin always have access
        if ($user->canSeeAllProjects()) {
            return true;
        }

        // Check explicit shares
        return $this->shares()
            ->where(function ($q) use ($user) {
                $q->where(function ($userShare) use ($user) {
                    $userShare->where('shareable_type', 'user')
                              ->where('shareable_id', (string)$user->id);
                })
                ->orWhere(function ($roleShare) use ($user) {
                    $roleShare->where('shareable_type', 'role')
                              ->where('shareable_id', $user->role);
                });
            })
            ->exists();
    }
}
