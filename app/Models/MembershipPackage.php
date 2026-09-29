<?php

namespace App\Models;

use Database\Factories\MembershipPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'duration_days', 'price', 'is_walk_in', 'access_allowed', 'status'])]
class MembershipPackage extends Model
{
    /** @use HasFactory<MembershipPackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_walk_in' => 'boolean',
            'access_allowed' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
