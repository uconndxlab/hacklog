<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectShare represents explicit visibility grants.
 * 
 * Projects can be shared with:
 * - Individual users (shareable_type='user', shareable_id=user.id)
 * - User roles (shareable_type='role', shareable_id='client'/'team')
 * 
 * Sharing grants visibility only - it does not imply edit rights.
 * Project-level roles (owner/contributor/viewer) control permissions.
 */
class ProjectShare extends Model
{
    protected $fillable = [
        'project_id',
        'shareable_type',
        'shareable_id',
    ];

    /**
     * Get the project being shared.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Check if this share is with a specific user.
     */
    public function isUserShare(): bool
    {
        return $this->shareable_type === 'user';
    }

    /**
     * Check if this share is with a role.
     */
    public function isRoleShare(): bool
    {
        return $this->shareable_type === 'role';
    }

    /**
     * Get the user if this is a user share.
     */
    public function getUser(): ?User
    {
        if ($this->isUserShare()) {
            return User::find($this->shareable_id);
        }
        return null;
    }

    /**
     * Get the role name if this is a role share.
     */
    public function getRoleName(): ?string
    {
        if ($this->isRoleShare()) {
            return $this->shareable_id;
        }
        return null;
    }
}
