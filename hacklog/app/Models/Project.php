<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    const STATUS_PLANNING = 'planning';
    const STATUS_ACTIVE = 'active';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ARCHIVED = 'archived';

    const STATUS_VALUES = [
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_ARCHIVED,
    ];

    const STAFFING_DEDICATED = 'dedicated';
    const STAFFING_SHARED = 'shared';

    const STAFFING_MODELS = [
        self::STAFFING_DEDICATED,
        self::STAFFING_SHARED,
    ];

    const TYPE_WEBSITE = 'website';
    const TYPE_WEBAPP = 'webapp';
    const TYPE_GRAPHIC_DESIGN = 'graphic_design';
    const TYPE_PROGRAM = 'program';
    const TYPE_OTHER = 'other';

    const TYPE_VALUES = [
        self::TYPE_WEBSITE,
        self::TYPE_WEBAPP,
        self::TYPE_GRAPHIC_DESIGN,
        self::TYPE_PROGRAM,
        self::TYPE_OTHER,
    ];

    const TYPE_LABELS = [
        self::TYPE_WEBSITE => 'Website',
        self::TYPE_WEBAPP => 'Webapp',
        self::TYPE_GRAPHIC_DESIGN => 'Graphic design',
        self::TYPE_PROGRAM => 'Program',
        self::TYPE_OTHER => 'Other',
    ];

    const CLIENT_CATEGORY_ADMINISTRATIVE = 'administrative_department';
    const CLIENT_CATEGORY_CENTER = 'center_institute';
    const CLIENT_CATEGORY_FEE_BASED = 'fee_based_program';
    const CLIENT_CATEGORY_SCHOOL = 'school_college_academic_department';

    const CLIENT_CATEGORY_VALUES = [
        self::CLIENT_CATEGORY_ADMINISTRATIVE,
        self::CLIENT_CATEGORY_CENTER,
        self::CLIENT_CATEGORY_FEE_BASED,
        self::CLIENT_CATEGORY_SCHOOL,
    ];

    const CLIENT_CATEGORY_LABELS = [
        self::CLIENT_CATEGORY_ADMINISTRATIVE => 'Administrative Department',
        self::CLIENT_CATEGORY_CENTER => 'Center/institute',
        self::CLIENT_CATEGORY_FEE_BASED => 'Fee-based program',
        self::CLIENT_CATEGORY_SCHOOL => 'Schools/colleges or academic department',
    ];

    const AFFILIATION_INTERNAL = 'internal';
    const AFFILIATION_EXTERNAL = 'external';

    const AFFILIATION_VALUES = [
        self::AFFILIATION_INTERNAL,
        self::AFFILIATION_EXTERNAL,
    ];

    const AFFILIATION_LABELS = [
        self::AFFILIATION_INTERNAL => 'Internal',
        self::AFFILIATION_EXTERNAL => 'External',
    ];

    protected $fillable = [
        'name',
        'description',
        'status',
        'staffing_model',
        'project_type',
        'department_id',
        'nested_department_id',
        'major_office_id',
        'client_pi',
        'client_category',
        'uconn_affiliation',
        'grant_value',
        'sponsor',
        'launch_date',
        'slack_webhook_url',
        'slack_channel_id',
        'slack_bot_enabled',
    ];

    protected $casts = [
        'status' => 'string',
        'staffing_model' => 'string',
        'project_type' => 'string',
        'client_category' => 'string',
        'uconn_affiliation' => 'string',
        'grant_value' => 'decimal:2',
        'launch_date' => 'date',
        'slack_webhook_url' => 'string',
        'slack_channel_id' => 'string',
        'slack_bot_enabled' => 'boolean',
    ];

    protected $attributes = [
        'staffing_model' => self::STAFFING_DEDICATED,
    ];

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
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

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->orderBy('created_at', 'desc');
    }

    public function favoritedByUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_favorites')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function nestedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'nested_department_id');
    }

    public function majorOffice(): BelongsTo
    {
        return $this->belongsTo(MajorOffice::class);
    }

    public function projectTypeLabel(): ?string
    {
        return self::TYPE_LABELS[$this->project_type] ?? null;
    }

    public function clientCategoryLabel(): ?string
    {
        return self::CLIENT_CATEGORY_LABELS[$this->client_category] ?? null;
    }

    public function uconnAffiliationLabel(): ?string
    {
        return self::AFFILIATION_LABELS[$this->uconn_affiliation] ?? null;
    }

    public function scopeWithTag(Builder $query, int $tagId): Builder
    {
        return $query->whereHas('tags', function (Builder $tagQuery) use ($tagId) {
            $tagQuery->where('tags.id', $tagId);
        });
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
