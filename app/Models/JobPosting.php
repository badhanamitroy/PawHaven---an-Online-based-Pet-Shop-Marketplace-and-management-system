<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class JobPosting extends Model
{
    public const POSITIONS = ['seller', 'manager'];

    public const STATUSES = ['draft', 'published', 'closed', 'archived'];

    protected $fillable = [
        'company_id', 'created_by_user_id', 'title', 'slug', 'position_type', 'summary',
        'description', 'responsibilities', 'requirements', 'employment_type', 'openings',
        'application_deadline', 'status', 'published_at', 'closed_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'openings' => 'integer',
            'application_deadline' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function scopePubliclyOpen(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $query) => $query->whereNull('application_deadline')
                ->orWhereDate('application_deadline', '>=', today()));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'job_posting_branches')
            ->withPivot('company_id')->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === 'published'
            && ($this->application_deadline === null || $this->application_deadline->isToday() || $this->application_deadline->isFuture());
    }
}
