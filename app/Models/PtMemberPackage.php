<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'member_id',
    'pt_package_id',
    'sale_id',
    'total_sessions',
    'used_sessions',
    'price',
    'purchased_at',
    'expires_at',
    'status',
    'notes',
    'created_by',
    'updated_by',
])]
class PtMemberPackage extends Model
{
    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'used_sessions' => 'integer',
            'price' => 'decimal:2',
            'purchased_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PtPackage::class, 'pt_package_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PtSession::class);
    }

    public function remainingSessions(): int
    {
        return max(0, $this->total_sessions - $this->used_sessions);
    }

    public function isActive(): bool
    {
        if ($this->status !== RecordStatus::Active->value) {
            return false;
        }

        if ($this->remainingSessions() <= 0) {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->greaterThanOrEqualTo(now()->startOfDay());
    }
}
