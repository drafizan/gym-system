<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'phone',
    'email',
    'specialization',
    'commission_per_session',
    'joined_at',
    'status',
    'notes',
])]
class PtTrainer extends Model
{
    protected function casts(): array
    {
        return [
            'commission_per_session' => 'decimal:2',
            'joined_at' => 'date',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PtSession::class, 'trainer_id');
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active->value;
    }
}
