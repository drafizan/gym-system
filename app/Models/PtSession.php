<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'check_in_token',
    'pt_member_package_id',
    'trainer_id',
    'member_id',
    'session_date',
    'scheduled_start_at',
    'scheduled_end_at',
    'duration_minutes',
    'status',
    'commission_amount',
    'notes',
    'recorded_by',
])]
class PtSession extends Model
{
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'duration_minutes' => 'integer',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function memberPackage(): BelongsTo
    {
        return $this->belongsTo(PtMemberPackage::class, 'pt_member_package_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(PtTrainer::class, 'trainer_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }
}
