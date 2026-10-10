<?php

namespace App\Http\Controllers;

use App\Enums\AccessSyncAction;
use App\Enums\MembershipStatus;
use App\Http\Requests\MembershipAssignmentRequest;
use App\Models\AccessSyncLog;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPackage;
use App\Support\Audit;
use App\Support\MembershipPeriod;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MemberMembershipController extends Controller
{
    public function create(Member $member): View
    {
        return view('memberships.assign', [
            'member' => $member,
            'packages' => $this->activePackages(),
            'membership' => null,
            'mode' => 'assign',
        ]);
    }

    public function store(MembershipAssignmentRequest $request, Member $member): RedirectResponse
    {
        $package = MembershipPackage::query()->findOrFail($request->integer('membership_package_id'));
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();

        $membership = $this->createMembership($request, $member, $package, $startDate, MembershipPeriod::endDateFromStart($startDate, $package->duration_days), 'assigned');

        return redirect()->route('members.show', $member)->with('success', 'Membership assigned successfully.');
    }

    public function renew(Member $member, MemberMembership $memberMembership): View
    {
        abort_unless($memberMembership->member_id === $member->id, 404);

        return view('memberships.assign', [
            'member' => $member,
            'packages' => $this->activePackages(),
            'membership' => $memberMembership->load('package'),
            'mode' => 'renew',
        ]);
    }

    public function storeRenewal(MembershipAssignmentRequest $request, Member $member, MemberMembership $memberMembership): RedirectResponse
    {
        abort_unless($memberMembership->member_id === $member->id, 404);

        $package = MembershipPackage::query()->findOrFail($request->integer('membership_package_id'));
        $validated = $request->validated();
        $renewalDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = $memberMembership->end_date->greaterThanOrEqualTo($renewalDate)
            ? MembershipPeriod::endDateAfterExistingExpiry($memberMembership->end_date, $package->duration_days)
            : MembershipPeriod::endDateFromStart($renewalDate, $package->duration_days);

        $membership = $this->createMembership($request, $member, $package, $renewalDate, $endDate, 'renewed');

        return redirect()->route('members.show', $member)->with('success', 'Membership renewed successfully.');
    }

    public function suspend(Request $request, Member $member, MemberMembership $memberMembership): RedirectResponse
    {
        abort_unless($memberMembership->member_id === $member->id, 404);

        $oldValues = $memberMembership->toArray();
        $memberMembership->update([
            'status' => MembershipStatus::Suspended->value,
            'updated_by' => $request->user()->id,
        ]);

        Audit::record($request, 'memberships', 'suspended', MemberMembership::class, $memberMembership->id, $oldValues, $memberMembership->fresh()->toArray());
        $this->createAccessSync($memberMembership->fresh());

        return back()->with('success', 'Membership suspended successfully.');
    }

    public function expiringSoon(Request $request): View
    {
        $today = now()->toDateString();
        $threshold = now()->addDays(app(SystemSettings::class)->integer('expiring_soon_days'))->toDateString();

        return view('memberships.worklist', [
            'title' => 'Expiring Soon',
            'memberships' => MemberMembership::query()
                ->with(['member', 'package'])
                ->where('status', MembershipStatus::Active->value)
                ->whereBetween('end_date', [$today, $threshold])
                ->orderBy('end_date')
                ->paginate(15),
        ]);
    }

    public function expired(Request $request): View
    {
        return view('memberships.worklist', [
            'title' => 'Expired Members',
            'memberships' => MemberMembership::query()
                ->with(['member', 'package'])
                ->where(function ($query): void {
                    $query->where('status', MembershipStatus::Expired->value)
                        ->orWhere(function ($query): void {
                            $query->where('status', MembershipStatus::Active->value)
                                ->whereDate('end_date', '<', now()->toDateString());
                        });
                })
                ->orderBy('end_date')
                ->paginate(15),
        ]);
    }

    private function createMembership(MembershipAssignmentRequest $request, Member $member, MembershipPackage $package, Carbon $startDate, Carbon $endDate, string $auditAction): MemberMembership
    {
        $membership = MemberMembership::query()->create([
            'member_id' => $member->id,
            'membership_package_id' => $package->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => MembershipStatus::Active->value,
            'payment_status' => $request->validated()['payment_status'],
            'amount' => $request->input('amount', $package->price),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Audit::record($request, 'memberships', $auditAction, MemberMembership::class, $membership->id, null, $membership->toArray());
        $this->createAccessSync($membership);

        return $membership;
    }

    private function createAccessSync(MemberMembership $membership): void
    {
        $membership->loadMissing(['member.activeRfidCard', 'package']);
        $card = $membership->member?->activeRfidCard;

        if (! $card) {
            return;
        }

        $action = $membership->status === MembershipStatus::Active->value && $membership->package?->access_allowed
            ? AccessSyncAction::EnableCard->value
            : AccessSyncAction::DisableCard->value;

        AccessSyncLog::query()->create([
            'member_id' => $membership->member_id,
            'member_membership_id' => $membership->id,
            'rfid_card_id' => $card->id,
            'action' => $action,
            'status' => 'pending',
            'payload' => [
                'member_no' => $membership->member?->member_no,
                'card_number' => $card->card_number,
                'rfid_card_number' => $card->card_number,
                'card_status' => $card->status,
                'membership_status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
                'start_at' => $membership->accessStartsAt()->format('Y-m-d H:i:s'),
                'end_date' => $membership->end_date?->toDateString(),
            ],
        ]);
    }

    /**
     * @return Collection<int, MembershipPackage>
     */
    private function activePackages()
    {
        return MembershipPackage::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
