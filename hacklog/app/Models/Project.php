<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    const STATUS_PLANNING = 'planning';

    const STATUS_ACTIVE = 'active';

    const STATUS_ON_HOLD = 'on_hold';

    const STATUS_COMPLETED = 'completed';

    const STATUS_ARCHIVED = 'archived';

    const DEFAULT_STATUS_LABELS = [
        self::STATUS_PLANNING => 'Planning',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ON_HOLD => 'On Hold',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    const DEFAULT_STATUS_COLORS = [
        self::STATUS_PLANNING => '#0dcaf0',
        self::STATUS_ACTIVE => '#198754',
        self::STATUS_ON_HOLD => '#ffc107',
        self::STATUS_COMPLETED => '#6482b4',
        self::STATUS_ARCHIVED => '#6c757d',
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

    const DEFAULT_TYPE_LABELS = [
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
        'has_grant',
        'grant_value',
        'sponsor',
        'launch_date',
        'slack_webhook_url',
        'slack_channel_id',
        'slack_bot_enabled',
        'honeycrisp_project_id',
        'honeycrisp_project_name',
        'honeycrisp_billed_total_cents',
        'honeycrisp_billed_fetched_at',
    ];

    protected $casts = [
        'status' => 'string',
        'staffing_model' => 'string',
        'project_type' => 'string',
        'client_category' => 'string',
        'uconn_affiliation' => 'string',
        'has_grant' => 'boolean',
        'grant_value' => 'decimal:2',
        'launch_date' => 'date',
        'slack_webhook_url' => 'string',
        'slack_channel_id' => 'string',
        'slack_bot_enabled' => 'boolean',
        'honeycrisp_project_id' => 'integer',
        'honeycrisp_project_name' => 'string',
        'honeycrisp_billed_total_cents' => 'integer',
        'honeycrisp_billed_fetched_at' => 'datetime',
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

    public function statusDefinition(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'status', 'key');
    }

    public function typeDefinition(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type', 'key');
    }

    public static function statusDefinitions()
    {
        return ProjectStatus::definitions();
    }

    public static function typeDefinitions()
    {
        return ProjectType::definitions();
    }

    public static function typeValues(): array
    {
        return array_keys(static::typeLabels());
    }

    public static function typeLabels(): array
    {
        $labels = static::typeDefinitions()->pluck('name', 'key')->all();

        if (! Schema::hasTable('projects')) {
            return $labels;
        }

        // Keep manually orphaned legacy values visible and valid until they are
        // explicitly reassigned to a configured type.
        foreach (static::query()->whereNotNull('project_type')->distinct()->pluck('project_type') as $key) {
            $labels[$key] ??= str($key)->replace('_', ' ')->title().' (unconfigured)';
        }

        return $labels;
    }

    public static function statusValues(): array
    {
        return static::statusDefinitions()->pluck('key')->all();
    }

    public static function statusLabels(): array
    {
        return static::statusDefinitions()->pluck('name', 'key')->all();
    }

    public static function statusColors(): array
    {
        return static::statusDefinitions()->pluck('color', 'key')->all();
    }

    public static function statusTextColors(): array
    {
        return static::statusDefinitions()->mapWithKeys(fn (ProjectStatus $status) => [
            $status->key => $status->textColor(),
        ])->all();
    }

    public static function activeViewStatusValues(): array
    {
        $definitions = static::statusDefinitions();
        $activeStatuses = $definitions
            ->where('show_in_active_views', true)
            ->pluck('key')
            ->all();

        if ($activeStatuses !== []) {
            return $activeStatuses;
        }

        // Protect day-to-day views if legacy/manual data leaves every status disabled.
        $fallback = $definitions->firstWhere('key', self::STATUS_ACTIVE)
            ?? $definitions->first();

        return $fallback ? [$fallback->key] : [];
    }

    public function statusLabel(): string
    {
        return $this->statusDefinition?->name
            ?? static::statusLabels()[$this->status]
            ?? str($this->status)->replace('_', ' ')->title()->toString();
    }

    public function statusColor(): string
    {
        return $this->statusDefinition?->color
            ?? static::statusColors()[$this->status]
            ?? '#6c757d';
    }

    public function statusTextColor(): string
    {
        return $this->statusDefinition?->textColor() ?? '#ffffff';
    }

    public function projectTypeLabel(): ?string
    {
        if ($this->project_type === null) {
            return null;
        }

        return $this->typeDefinition?->name
            ?? static::typeLabels()[$this->project_type]
            ?? null;
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
                        ->where('shareable_id', (string) $user->id);
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
                        ->where('shareable_id', (string) $user->id);
                })
                    ->orWhere(function ($roleShare) use ($user) {
                        $roleShare->where('shareable_type', 'role')
                            ->where('shareable_id', $user->role);
                    });
            })
            ->exists();
    }
}
