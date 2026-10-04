<?php

namespace App\Support;

use App\Enums\AccessSyncAction;
use App\Enums\RecordStatus;
use App\Enums\RfidCardStatus;
use App\Models\AccessSyncLog;
use App\Models\Member;
use App\Models\RfidCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfidCardManager
{
    public function assign(Request $request, Member $member, string $cardNumber, ?string $remarks = null): RfidCard
    {
        $cardNumber = $this->normalizeCardNumber($cardNumber);

        return DB::transaction(function () use ($request, $member, $cardNumber, $remarks): RfidCard {
            $member->refresh();
            $this->ensureMemberIsActive($member);
            $this->ensureCardNumberAvailable($cardNumber);
            $this->ensureMemberHasNoActiveCard($member);

            $card = RfidCard::query()->create([
                'member_id' => $member->id,
                'card_number' => $cardNumber,
                'status' => RfidCardStatus::Active->value,
                'assigned_by' => $request->user()?->id,
                'assigned_at' => now(),
                'remarks' => $remarks,
            ]);

            $member->forceFill(['rfid_card_number' => $cardNumber])->save();

            $this->queueSync($card, AccessSyncAction::AddCard);
            Audit::record($request, 'rfid_cards', 'assigned', RfidCard::class, $card->id, null, $card->toArray());

            return $card;
        });
    }

    public function replace(Request $request, RfidCard $card, string $newCardNumber, ?string $remarks = null): RfidCard
    {
        $newCardNumber = $this->normalizeCardNumber($newCardNumber);

        return DB::transaction(function () use ($request, $card, $newCardNumber, $remarks): RfidCard {
            $card->refresh();
            $this->ensureMemberIsActive($card->member);
            $this->ensureCardIsActive($card);
            $this->ensureCardNumberAvailable($newCardNumber, $card->id);

            $oldValues = $card->toArray();

            $card->forceFill([
                'card_number' => $newCardNumber,
                'status' => RfidCardStatus::Active->value,
                'deactivated_at' => null,
                'replaced_by_id' => null,
                'remarks' => $remarks,
            ])->save();

            $card->member?->forceFill(['rfid_card_number' => $newCardNumber])->save();

            $this->queueSync($card, AccessSyncAction::UpdateCard);
            Audit::record($request, 'rfid_cards', 'updated', RfidCard::class, $card->id, $oldValues, $card->fresh()->toArray());

            return $card->fresh();
        });
    }

    public function deactivate(Request $request, RfidCard $card, ?string $remarks = null): RfidCard
    {
        return $this->closeCard($request, $card, RfidCardStatus::Inactive, AccessSyncAction::DisableCard, 'deactivated', $remarks);
    }

    public function block(Request $request, RfidCard $card, ?string $remarks = null): RfidCard
    {
        return $this->closeCard($request, $card, RfidCardStatus::Blocked, AccessSyncAction::DisableCard, 'blocked', $remarks);
    }

    public function syncMemberCardInput(Request $request, Member $member, ?string $cardNumber): void
    {
        $cardNumber = $this->normalizeCardNumber((string) $cardNumber);
        $activeCard = RfidCard::query()
            ->where('member_id', $member->id)
            ->where('status', RfidCardStatus::Active->value)
            ->latest('id')
            ->first();
        $currentCardNumber = $activeCard?->card_number ?? $this->normalizeCardNumber((string) $member->rfid_card_number);

        if ($cardNumber === $currentCardNumber) {
            return;
        }

        if ($cardNumber === '') {
            if ($activeCard) {
                $this->deactivate($request, $activeCard, 'Removed from member profile');

                return;
            }

            $member->forceFill(['rfid_card_number' => null])->save();

            return;
        }

        if ($activeCard) {
            $this->replace($request, $activeCard, $cardNumber, 'Updated from member profile');

            return;
        }

        $this->assign($request, $member, $cardNumber, 'Assigned from member profile');
    }

    private function closeCard(
        Request $request,
        RfidCard $card,
        RfidCardStatus $status,
        AccessSyncAction $syncAction,
        string $auditAction,
        ?string $remarks = null,
    ): RfidCard {
        return DB::transaction(function () use ($request, $card, $status, $syncAction, $auditAction, $remarks): RfidCard {
            $card->refresh();
            $this->ensureCardIsActive($card);

            $oldValues = $card->toArray();

            $card->forceFill([
                'status' => $status->value,
                'deactivated_at' => now(),
                'remarks' => $remarks,
            ])->save();

            $card->member?->forceFill(['rfid_card_number' => null])->save();

            $this->queueSync($card, $syncAction);
            Audit::record($request, 'rfid_cards', $auditAction, RfidCard::class, $card->id, $oldValues, $card->fresh()->toArray());

            return $card;
        });
    }

    private function normalizeCardNumber(string $cardNumber): string
    {
        return trim($cardNumber);
    }

    private function ensureCardNumberAvailable(string $cardNumber, ?int $ignoreCardId = null): void
    {
        if ($cardNumber === '') {
            throw ValidationException::withMessages([
                'card_number' => 'Card number is required.',
            ]);
        }

        $exists = RfidCard::query()
            ->where('card_number', $cardNumber)
            ->where('status', RfidCardStatus::Active->value)
            ->when($ignoreCardId, fn ($query) => $query->whereKeyNot($ignoreCardId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'card_number' => 'This RFID card is already active.',
            ]);
        }
    }

    private function ensureMemberHasNoActiveCard(Member $member): void
    {
        $exists = RfidCard::query()
            ->where('member_id', $member->id)
            ->where('status', RfidCardStatus::Active->value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'member_id' => 'This member already has an active RFID card.',
            ]);
        }
    }

    private function ensureMemberIsActive(?Member $member): void
    {
        if (! $member || $member->status !== RecordStatus::Active->value) {
            throw ValidationException::withMessages([
                'card_number' => 'RFID cards can only be activated for active members.',
            ]);
        }
    }

    private function ensureCardIsActive(RfidCard $card): void
    {
        if (! $card->isActive()) {
            throw ValidationException::withMessages([
                'card_number' => 'Only active RFID cards can be changed.',
            ]);
        }
    }

    private function queueSync(RfidCard $card, AccessSyncAction $action): void
    {
        $card->loadMissing('member.latestMembership');
        $membership = $card->member?->latestMembership;

        AccessSyncLog::query()->create([
            'member_id' => $card->member_id,
            'member_membership_id' => $membership?->id,
            'rfid_card_id' => $card->id,
            'action' => $action->value,
            'status' => 'pending',
            'payload' => [
                'member_no' => $card->member?->member_no,
                'card_number' => $card->card_number,
                'card_status' => $card->status,
                'membership_status' => $membership?->status,
                'start_date' => $membership?->start_date?->toDateString(),
                'end_date' => $membership?->end_date?->toDateString(),
            ],
        ]);
    }
}
