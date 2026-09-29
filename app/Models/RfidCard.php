<?php

namespace App\Models;

use App\Enums\RfidCardStatus;
use Database\Factories\RfidCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id',
    'card_number',
    'status',
    'assigned_by',
    'assigned_at',
    'deactivated_at',
    'replaced_by_id',
    'remarks',
])]
class RfidCard extends Model
{
    /** @use HasFactory<RfidCardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class, 'replaced_by_id');
    }

    public function isActive(): bool
    {
        return $this->status === RfidCardStatus::Active->value;
    }
}
