<?php

namespace App\Models;

use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\BusinessCompanyAssignmentAware;
use App\Models\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $description
 * @property string|null $ip_address
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class AdminAuditLog extends Model implements BusinessBranchScoped, BusinessCompanyAssignmentAware
{
    use HasBusinessScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'action',
        'description',
        'ip_address',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function businessBranchColumn(): ?string
    {
        return 'branch_id';
    }

    public function shouldAssignDefaultBusinessCompany(?User $user): bool
    {
        return $user instanceof User && $user->isStaff();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to record an audit log entry.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function record(string $action, string $description, ?array $payload = null): self
    {
        $companyId = isset($payload['company_id']) ? (int) $payload['company_id'] : null;
        $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : null;

        if ($companyId === null && isset($payload['product_id'])) {
            $record = Product::query()->withoutGlobalScopes()->whereKey($payload['product_id'])->first();
            $companyId = $record?->company_id;
            $branchId ??= $record?->branch_id;
        }

        if ($companyId === null && isset($payload['order_id'])) {
            $record = Order::query()->withoutGlobalScopes()->whereKey($payload['order_id'])->first();
            $companyId = $record?->company_id;
            $branchId ??= $record?->branch_id;
        }

        if ($companyId === null && isset($payload['category_id'])) {
            $record = ProductCategory::query()->withoutGlobalScopes()->whereKey($payload['category_id'])->first();
            $companyId = $record?->company_id;
        }

        if ($companyId === null && $branchId !== null) {
            $branch = Branch::query()->withoutGlobalScopes()->whereKey($branchId)->first();
            $companyId = $branch?->company_id;
        }

        return self::create([
            'user_id' => auth()->id(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'payload' => $payload,
        ]);
    }
}
