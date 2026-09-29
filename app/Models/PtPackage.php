<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'sessions_count',
    'price',
    'commission_per_session',
    'validity_days',
    'status',
    'description',
])]
class PtPackage extends Model
{
    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'price' => 'decimal:2',
            'commission_per_session' => 'decimal:2',
            'validity_days' => 'integer',
        ];
    }

    public function memberPackages(): HasMany
    {
        return $this->hasMany(PtMemberPackage::class);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active->value;
    }
}
