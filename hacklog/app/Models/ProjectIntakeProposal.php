<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIntakeProposal extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_DISMISSED = 'dismissed';

    /**
     * Human-readable labels for optional dismissal reasons.
     * Keys stored in disposition_reason; used in UI dropdowns.
     */
    const DISMISSAL_REASONS = [
        'not_actionable'  => 'Not actionable',
        'already_handled' => 'Already handled',
        'duplicate'       => 'Duplicate',
        'not_needed'      => 'Not needed',
    ];

    protected $fillable = [
        'project_intake_id',
        'title',
        'description',
        'suggested_phase_id',
        'suggested_assignee_id',
        'due_date',
        'confidence',
        'source_excerpt',
        'possible_duplicate_of',
        'status',
        'disposition_reason',
        'created_task_id',
    ];

    protected $casts = [
        'due_date'              => 'date',
        'confidence'            => 'float',
        'suggested_phase_id'    => 'integer',
        'suggested_assignee_id' => 'integer',
        'created_task_id'       => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function intake(): BelongsTo
    {
        return $this->belongsTo(ProjectIntake::class, 'project_intake_id');
    }

    public function suggestedPhase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'suggested_phase_id');
    }

    public function suggestedAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_assignee_id');
    }

    public function createdTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'created_task_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function dispositionLabel(): ?string
    {
        if ($this->disposition_reason === null) {
            return null;
        }

        return self::DISMISSAL_REASONS[$this->disposition_reason] ?? $this->disposition_reason;
    }
}
