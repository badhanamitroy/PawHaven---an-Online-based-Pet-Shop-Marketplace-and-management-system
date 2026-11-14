<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EmploymentOffer extends Model
{
    protected $fillable = [
        'job_application_id', 'company_id', 'branch_id', 'offered_by_user_id', 'employee_user_id',
        'active_application_id', 'position_type', 'candidate_email', 'employment_terms', 'compensation',
        'acceptance_deadline', 'status', 'token_hash', 'resend_count', 'issued_at',
        'accepted_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash', 'active_application_id', 'candidate_email'];

    protected function casts(): array
    {
        return [
            'resend_count' => 'integer',
            'acceptance_deadline' => 'datetime',
            'issued_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(EmploymentOfferOtpChallenge::class, 'offer_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'offered'
            && $this->revoked_at === null
            && $this->acceptance_deadline !== null
            && $this->acceptance_deadline->isFuture();
    }
}
