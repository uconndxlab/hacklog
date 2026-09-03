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
        'slack_id',
        'name',
        'nicknames',
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
            'nicknames' => 'array',
        ];
    }

    /**
     * Parse a comma-separated nicknames string into a unique, trimmed list.
     *
     * @return list<string>
     */
    public static function parseNicknames(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $nicknames = [];
        $seen = [];

        foreach (explode(',', $value) as $nickname) {
            $nickname = trim($nickname);

            if ($nickname === '') {
                continue;
            }

            $key = strtolower($nickname);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $nicknames[] = $nickname;
        }

        return $nicknames;
    }

    /**
     * Match a search term against nickname values.
     */
    public function scopeWhereNicknameLike($query, string $search)
    {
        $like = '%'.$search.'%';
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->whereExists(function ($sub) use ($like) {
                $sub->selectRaw('1')
                    ->fromRaw('json_each(users.nicknames)')
                    ->where('json_each.value', 'like', $like);
            });
        }

        return $query->whereRaw('JSON_SEARCH(nicknames, "one", ?) IS NOT NULL', [$like]);
    }

    /**
     * Combined lowercase name + nicknames text for picker search.
     */
    public function searchText(): string
    {
        return strtolower(trim($this->name.' '.implode(' ', $this->nicknames ?? [])));
    }

    /**
     * Comma-separated nicknames for form fields.
     */
    public function nicknamesAsString(): string
    {
        return implode(', ', $this->nicknames ?? []);
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
        
        return $lastActivity ? \Carbon\Carbon::createFromTimestamp($lastActivity, 'UTC')
            ->setTimezone(config('app.timezone')) : null;
    }

    /**
     * Get the user's latest activity log entry timestamp.
     */
    public function getLatestActivityLogAttribute()
    {
        // Get the latest from project activities
        $projectActivity = \DB::table('project_activities')
            ->where('user_id', $this->id)
            ->max('created_at');
        
        // Get the latest from task activities
        $taskActivity = \DB::table('task_activities')
            ->where('user_id', $this->id)
            ->max('created_at');
        
        // Get the latest from task comments
        $taskComment = \DB::table('task_comments')
            ->where('user_id', $this->id)
            ->max('created_at');
        
        // Get the latest from task attachments
        $taskAttachment = \DB::table('task_attachments')
            ->where('user_id', $this->id)
            ->max('created_at');
        
        // Get the latest task created by this user
        $taskCreated = \DB::table('tasks')
            ->where('created_by', $this->id)
            ->max('created_at');
        
        // Get the latest task updated by this user
        $taskUpdated = \DB::table('tasks')
            ->where('updated_by', $this->id)
            ->max('updated_at');
        
        // Return the most recent one
        $timestamps = array_filter([
            $projectActivity,
            $taskActivity,
            $taskComment,
            $taskAttachment,
            $taskCreated,
            $taskUpdated
        ]);
        
        if (empty($timestamps)) {
            return null;
        }
        
        // Parse as UTC and convert to app timezone
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', max($timestamps), 'UTC')
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Get the most recent activity timestamp (activity log or session).
     */
    public function getMostRecentActivityAttribute()
    {
        $activityLog = $this->latest_activity_log;
        $sessionActivity = $this->last_activity;
        
        if ($activityLog && $sessionActivity) {
            return $activityLog->gt($sessionActivity) ? $activityLog : $sessionActivity;
        }
        
        return $activityLog ?: $sessionActivity;
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }

    public function favoriteProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_favorites')->withTimestamps();
    }

    public function projectShares(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectShare::class, 'shareable_id', 'id')
            ->where('shareable_type', 'user');
    }
}
