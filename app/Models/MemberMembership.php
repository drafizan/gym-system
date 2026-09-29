<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\MemberMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id',
    'membership_package_id',
    'start_date',
    'end_date',
    'status',
    'payment_status',
    'amount',
    'created_by',
    'updated_by',
])]
class MemberMembership extends Model
{
    /** @use HasFactory<MemberMembershipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MembershipPackage::class, 'membership_package_id');
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active->value;
    }
}
