<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\RfidCardStatus;
use App\Support\SystemSettings;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'member_no',
    'full_name',
    'ic_passport_no',
    'date_of_birth',
    'gender',
    'phone',
    'email',
    'address',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_phone',
    'photo_path',
    'rfid_card_number',
    'status',
    'remarks',
    'referred_by_member_id',
    'created_by',
    'updated_by',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            $member->member_no ??= static::nextMemberNumber();
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public static function nextMemberNumber(): string
    {
        $prefix = 'GMG'.now()->format('ym');
        $latest = static::query()
            ->where('member_no', 'like', $prefix.'%')
            ->orderByDesc('member_no')
            ->value('member_no');

        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'referred_by_member_id');
    }

    public function referredMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'referred_by_member_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class);
    }

    public function ptPackages(): HasMany
    {
        return $this->hasMany(PtMemberPackage::class);
    }

    public function ptSessions(): HasMany
    {
        return $this->hasMany(PtSession::class);
    }

    public function latestMembership(): HasOne
    {
        return $this->hasOne(MemberMembership::class)->latestOfMany('end_date');
    }

    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class);
    }

    public function activeRfidCard(): HasOne
    {
        return $this->hasOne(RfidCard::class)
            ->where('status', RfidCardStatus::Active->value)
            ->latestOfMany();
    }

    public function latestAccessSyncLog(): HasOne
    {
        return $this->hasOne(AccessSyncLog::class)->latestOfMany();
    }

    public function isSuspended(): bool
    {
        return $this->status === RecordStatus::Suspended->value;
    }

    public function displayStatus(): string
    {
        if ($this->status !== RecordStatus::Active->value) {
            return $this->status;
        }

        $membership = $this->latestMembership;

        if (! $membership) {
            return $this->status;
        }

        if (
            $membership->status === MembershipStatus::Expired->value
            || (
                $membership->status === MembershipStatus::Active->value
                && $membership->end_date->lt(now()->startOfDay())
            )
        ) {
            return MembershipStatus::Expired->value;
        }

        if (in_array($membership->status, [
            MembershipStatus::Suspended->value,
            MembershipStatus::Cancelled->value,
        ], true)) {
            return $membership->status;
        }

        if (
            $membership->status === MembershipStatus::Active->value
            && $membership->end_date->betweenIncluded(
                now()->startOfDay(),
                now()->addDays(app(SystemSettings::class)->integer('expiring_soon_days'))->endOfDay()
            )
        ) {
            return 'expiring';
        }

        return $this->status;
    }

    public function displayStatusTone(): string
    {
        return match ($this->displayStatus()) {
            RecordStatus::Active->value => 'success',
            'expiring' => 'warning',
            MembershipStatus::Expired->value => 'danger',
            default => 'muted-pill',
        };
    }
}
