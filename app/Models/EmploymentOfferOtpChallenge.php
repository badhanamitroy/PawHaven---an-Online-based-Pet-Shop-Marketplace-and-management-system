<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentOfferOtpChallenge extends Model
{
    protected $fillable = [
        'offer_id', 'active_offer_id', 'email', 'otp_hash', 'attempts', 'max_attempts',
        'expires_at', 'last_sent_at', 'verified_at', 'consumed_at', 'invalidated_at',
    ];

    protected $hidden = ['otp_hash', 'active_offer_id', 'email'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(EmploymentOffer::class, 'offer_id');
    }
}
