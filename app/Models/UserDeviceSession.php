<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string|null $previous_token_hash
 * @property Carbon|null $previous_token_valid_until
 * @property string $device_name
 * @property string|null $browser_name
 * @property string|null $browser_version
 * @property string|null $platform
 * @property string $device_type
 * @property string|null $ip_address
 * @property Carbon $last_activity_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_rotated_at
 */
#[Fillable([
    'user_id',
    'token_hash',
    'previous_token_hash',
    'previous_token_valid_until',
    'device_name',
    'browser_name',
    'browser_version',
    'platform',
    'device_type',
    'ip_address',
    'last_activity_at',
    'expires_at',
    'revoked_at',
    'last_rotated_at',
])]
#[Hidden(['token_hash', 'previous_token_hash'])]
class UserDeviceSession extends Model
{
    protected function casts(): array
    {
        return [
            'previous_token_valid_until' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_rotated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
