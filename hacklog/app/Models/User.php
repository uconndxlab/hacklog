<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Application-level roles
     * 
     * These define default project visibility:
     * - admin: Full system access, sees all projects
     * - team: Internal staff, sees all projects by default
     * - client: External users, sees only explicitly shared projects
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEAM = 'team';
    public const ROLE_CLIENT = 'client';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'netid',
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * Check if user has admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is internal team member.
     * Team members see all projects by default.
     */
    public function isTeam(): bool
    {
        return $this->role === self::ROLE_TEAM;
    }

    /**
     * Check if user is external client.
     * Clients only see explicitly shared projects.
     */
    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    /**
     * Check if user can see all projects by default.
     * True for admin and team, false for clients.
     */
    public function canSeeAllProjects(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_TEAM]);
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get the user's last activity timestamp from their most recent session.
     */
    public function getLastActivityAttribute()
    {
        $lastActivity = \DB::table('sessions')
            ->where('user_id', $this->id)
            ->max('last_activity');
        
        return $lastActivity ? \Carbon\Carbon::createFromTimestamp($lastActivity) : null;
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }
}
