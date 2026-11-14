<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    public const STATUSES = [
        'submitted', 'shortlisted', 'selected', 'offered', 'awaiting_owner_approval',
        'hired', 'rejected', 'withdrawn',
    ];

    protected $fillable = [
        'job_posting_id', 'preferred_branch_id', 'full_name', 'email', 'phone', 'address',
        'experience', 'motivation', 'cv_path', 'status', 'internal_notes',
        'active_submission_key', 'submitted_at', 'reviewed_at',
    ];

    protected $hidden = [
        'email', 'phone', 'address', 'experience', 'motivation', 'internal_notes',
        'cv_path', 'active_submission_key',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    public function preferredBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'preferred_branch_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(EmploymentOffer::class, 'job_application_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['hired', 'rejected', 'withdrawn'], true);
    }
}
