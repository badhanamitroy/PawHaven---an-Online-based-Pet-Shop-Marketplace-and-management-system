<?php

namespace App\Console\Commands;

use App\Models\UserDeviceSession;
use Illuminate\Console\Command;

class PruneUserDeviceSessions extends Command
{
    protected $signature = 'device-sessions:prune';

    protected $description = 'Remove expired and revoked device-session records after their retention period';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('device_sessions.retention_days', 30)));

        $deleted = UserDeviceSession::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('revoked_at', '<', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('revoked_at')
                            ->where('expires_at', '<', $cutoff);
                    });
            })
            ->delete();

        $this->info("Removed {$deleted} expired or revoked device-session record(s).");

        return self::SUCCESS;
    }
}
