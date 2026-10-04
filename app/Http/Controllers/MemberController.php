<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleType;
use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\MembershipPackage;
use App\Services\AccessSyncManager;
use App\Support\Audit;
use App\Support\RegistrationCheckout;
use App\Support\RfidCardManager;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        return $this->memberList($request);
    }

    public function suspended(Request $request): View
    {
        return $this->memberList($request, [
            'status' => RecordStatus::Suspended->value,
            'pageTitle' => 'Suspended Members',
            'listTitle' => 'Suspended Member List',
        ]);
    }

    public function photoCapture(Request $request): View
    {
        return $this->memberList($request, [
            'missingPhoto' => true,
            'pageTitle' => 'Photo Capture',
            'listTitle' => 'Members Without Photo',
        ]);
    }

    public function expired(Request $request): View
    {
        return $this->memberList($request, [
            'expiredMembership' => true,
            'pageTitle' => 'Expired Members',
            'listTitle' => 'Expired Member List',
        ]);
    }

    public function expiringSoon(Request $request): View
    {
        return $this->memberList($request, [
            'expiringSoonMembership' => true,
            'pageTitle' => 'Expiring Soon',
            'listTitle' => 'Expiring Soon Member List',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function memberList(Request $request, array $options = []): View
    {
        $search = trim((string) $request->query('search'));
        $filter = (string) $request->query('filter', '');

        $members = $this->memberListQuery($request, $options)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'search' => $search,
            'filter' => $filter,
            'pageTitle' => $options['pageTitle'] ?? 'Members',
            'listTitle' => $options['listTitle'] ?? 'Member List',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Builder<Member>
     */
    private function memberListQuery(Request $request, array $options = []): Builder
    {
        $search = trim((string) $request->query('search'));
        $filter = (string) $request->query('filter', '');

        return Member::query()
            ->with(['latestMembership'])
            ->when(! empty($options['status']), function ($query) use ($options): void {
                $query->where('status', $options['status']);
            })
            ->when(! empty($options['missingPhoto']), function ($query): void {
                $query->whereNull('photo_path');
            })
            ->when(! empty($options['expiredMembership']), function ($query): void {
                $this->applyMemberFilter($query, 'expired');
            })
            ->when(! empty($options['expiringSoonMembership']), function ($query): void {
                $this->applyMemberFilter($query, 'expiring');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('member_no', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ic_passport_no', 'like', "%{$search}%");
                });
            })
            ->when($filter !== '', fn ($query) => $this->applyMemberFilter($query, $filter));
    }

    private function applyMemberFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'active', 'expiring' => $query->where('status', RecordStatus::Active->value)
                ->whereHas('latestMembership', function ($query) use ($filter): void {
                    $query->where('status', MembershipStatus::Active->value)
                        ->where('payment_status', 'paid')
                        ->whereDate('start_date', '<=', today()->toDateString())
                        ->whereDate('end_date', '>=', today()->toDateString());

                    if ($filter === 'expiring') {
                        $query->whereDate('end_date', '<=', today()->addDays(app(SystemSettings::class)->integer('expiring_soon_days'))->toDateString());
                    }
                }),
            'expired' => $query->where('status', RecordStatus::Active->value)
                ->where(function ($query): void {
                    $query->whereDoesntHave('memberships')
                        ->orWhereHas('latestMembership', fn ($query) => $query
                            ->where('status', MembershipStatus::Expired->value)
                            ->orWhere(fn ($query) => $query
                                ->where('status', MembershipStatus::Active->value)
                                ->whereDate('end_date', '<', today()->toDateString())));
                }),
            'suspended' => $query->where('status', RecordStatus::Suspended->value),
            'missing_photo' => $query->whereNull('photo_path'),
            'has_rfid' => $query->where(fn ($query) => $query
                ->whereNotNull('rfid_card_number')
                ->orWhereHas('activeRfidCard')),
            'no_rfid' => $query->whereNull('rfid_card_number')
                ->whereDoesntHave('activeRfidCard'),
            default => null,
        };
    }

    public function export(Request $request): Response
    {
        $members = $this->memberListQuery($request)
            ->with(['latestMembership.package', 'activeRfidCard'])
            ->orderBy('member_no')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Member No', 'Name', 'Phone', 'Email', 'Status', 'Membership', 'Expiry Date', 'RFID Card']);

        foreach ($members as $member) {
            fputcsv($handle, [
                $member->member_no,
                $member->full_name,
                $member->phone,
                $member->email,
                str($member->displayStatus())->headline(),
                $member->latestMembership?->package?->name,
                $member->latestMembership?->end_date?->format('Y-m-d'),
                $member->activeRfidCard?->card_number ?? $member->rfid_card_number,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Audit::record($request, 'members', 'exported', null, null, null, [
            'count' => $members->count(),
            'filter' => $request->query('filter'),
            'search' => $request->query('search'),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="members-export-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'members_csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($validated['members_csv']->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($value) => str((string) $value)->lower()->replace([' ', '-'], '_')->toString(), $header);
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $payload = array_combine($header, $row);

            if (! is_array($payload) || empty($payload['full_name']) || empty($payload['phone'])) {
                $skipped++;

                continue;
            }

            $validator = Validator::make($payload, [
                'full_name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?60|0)(?:1[0-46-9][\s-]?\d{3,4}[\s-]?\d{4}|[3-9][\s-]?\d{7,8})$/'],
                'email' => ['nullable', 'email', 'max:255'],
                'gender' => ['nullable', 'in:male,female'],
            ]);

            if (
                $validator->fails()
                || Member::query()->where('phone', $payload['phone'])->exists()
                || (! empty($payload['ic_passport_no']) && Member::query()->where('ic_passport_no', $payload['ic_passport_no'])->exists())
                || (! empty($payload['rfid_card_number']) && Member::query()->where('rfid_card_number', $payload['rfid_card_number'])->exists())
            ) {
                $skipped++;

                continue;
            }

            Member::query()->create([
                'full_name' => $payload['full_name'],
                'phone' => $payload['phone'],
                'email' => $payload['email'] ?? null,
                'ic_passport_no' => $payload['ic_passport_no'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'address' => $payload['address'] ?? null,
                'rfid_card_number' => $payload['rfid_card_number'] ?? null,
                'status' => RecordStatus::Active->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $created++;
        }

        fclose($handle);

        Audit::record($request, 'members', 'imported', null, null, null, [
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return back()->with('success', "Member import completed. Created {$created}, skipped {$skipped}.");
    }

    public function create(): View
    {
        return view('members.create', [
            'member' => new Member,
            ...$this->memberFormOptions(true),
        ]);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $validated = $request->safe()->except([
            'registration_checkout',
            'photo',
            'captured_photo',
            'membership_package_id',
            'membership_start_date',
            'membership_end_date',
            'membership_amount',
            'membership_payment_method',
            'membership_payment_status',
            'rfid_card_number',
        ]);
        $rfidCardNumber = (string) $request->input('rfid_card_number', '');
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        $validated['photo_path'] = $this->storePhoto($request);

        $member = Member::query()->create($validated);

        Audit::record($request, 'members', 'created', Member::class, $member->id, null, $member->toArray());
        app(RfidCardManager::class)->syncMemberCardInput($request, $member, $rfidCardNumber);

        if ($request->boolean('registration_checkout')) {
            $token = (string) Str::uuid();
            $package = MembershipPackage::query()->findOrFail($request->integer('membership_package_id'));
            $request->session()->put('registration_checkouts.'.$token, [
                'member_id' => $member->id,
                'sale_type' => SaleType::MembershipSale->value,
                'membership_package_id' => $package->id,
                'start_date' => $request->input('membership_start_date') ?: now()->toDateString(),
                'end_date' => $request->input('membership_end_date'),
                'membership_amount' => $request->input('membership_amount') ?? $package->price,
                'payment_method' => $request->input('membership_payment_method') ?: 'cash',
                'registration_fee' => RegistrationCheckout::fee(),
                'registration_checkout_token' => $token,
            ]);

            return redirect()->route('sales.pos', ['registration_checkout' => $token])
                ->with('success', 'Member saved. Complete payment in POS to activate the membership.');
        }

        if ($request->boolean('save_and_add')) {
            return redirect()->route('members.create')->with('success', 'Member registered successfully. You can add another member.');
        }

        return redirect()->route('members.show', $member)->with('success', 'Member registered successfully.');
    }

    public function show(Member $member): View
    {
        return view('members.show', [
            'member' => $member->load(['creator', 'updater', 'referrer', 'latestMembership.package', 'activeRfidCard.assignedBy', 'latestAccessSyncLog']),
        ]);
    }

    public function edit(Member $member): View
    {
        return view('members.edit', [
            'member' => $member->load(['latestMembership.package']),
            ...$this->memberFormOptions(true, $member),
        ]);
    }

    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        $validated = $request->safe()->except([
            'registration_checkout',
            'save_profile_only',
            'photo',
            'captured_photo',
            'membership_package_id',
            'membership_start_date',
            'membership_end_date',
            'membership_amount',
            'membership_payment_method',
            'membership_payment_status',
            'rfid_card_number',
        ]);
        $rfidCardNumber = (string) $request->input('rfid_card_number', '');
        $oldValues = $member->toArray();
        $validated['updated_by'] = $request->user()->id;

        if ($photoPath = $this->storePhoto($request)) {
            $validated['photo_path'] = $photoPath;
        }

        $member->update($validated);
        app(RfidCardManager::class)->syncMemberCardInput($request, $member->fresh(), $rfidCardNumber);

        Audit::record($request, 'members', 'updated', Member::class, $member->id, $oldValues, $member->fresh()->toArray());

        if ($request->boolean('registration_checkout')) {
            $token = (string) Str::uuid();
            $package = MembershipPackage::query()->findOrFail($request->integer('membership_package_id'));
            $request->session()->put('registration_checkouts.'.$token, [
                'member_id' => $member->id,
                'sale_type' => $member->memberships()->exists() ? SaleType::MembershipRenewal->value : SaleType::MembershipSale->value,
                'membership_package_id' => $package->id,
                'start_date' => $request->input('membership_start_date') ?: now()->toDateString(),
                'end_date' => $request->input('membership_end_date'),
                'membership_amount' => $request->input('membership_amount') ?? $package->price,
                'payment_method' => $request->input('membership_payment_method') ?: 'cash',
                'registration_fee' => 0,
                'registration_checkout_token' => $token,
            ]);

            return redirect()->route('sales.pos', ['registration_checkout' => $token])
                ->with('success', 'Member updated. Complete the membership payment in POS.');
        }

        return redirect()->route('members.show', $member)->with('success', 'Member updated successfully.');
    }

    public function suspend(Request $request, Member $member, AccessSyncManager $accessSync): RedirectResponse
    {
        $cardDeactivated = false;

        DB::transaction(function () use ($request, $member, &$cardDeactivated): void {
            $oldValues = $member->only(['status']);
            $member->update([
                'status' => RecordStatus::Suspended->value,
                'updated_by' => $request->user()->id,
            ]);

            if ($activeCard = $member->activeRfidCard()->first()) {
                app(RfidCardManager::class)->deactivate($request, $activeCard, 'Member suspended');
                $cardDeactivated = true;
            }

            Audit::record($request, 'members', 'suspended', Member::class, $member->id, $oldValues, $member->only(['status']));
        });

        if ($cardDeactivated) {
            $accessSync->syncPending(50);
        }

        return back()->with('success', 'Member suspended successfully.');
    }

    public function reactivate(Request $request, Member $member): RedirectResponse
    {
        $oldValues = $member->only(['status']);
        $member->update([
            'status' => RecordStatus::Active->value,
            'updated_by' => $request->user()->id,
        ]);

        Audit::record($request, 'members', 'reactivated', Member::class, $member->id, $oldValues, $member->only(['status']));

        return back()->with('success', 'Member reactivated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function memberFormOptions(bool $allowMembershipSetup, ?Member $member = null): array
    {
        return [
            'registrationFee' => RegistrationCheckout::fee(),
            'membershipPackages' => MembershipPackage::query()
                ->where('status', 'active')
                ->where('name', '!=', 'Registration Fee')
                ->orderBy('name')
                ->get(),
            'paymentMethods' => app(SystemSettings::class)->paymentMethods(),
            'allowMembershipSetup' => $allowMembershipSetup,
            'activeReferrers' => Member::query()
                ->where('status', RecordStatus::Active->value)
                ->when($member, fn ($query) => $query->whereKeyNot($member->id))
                ->orderBy('full_name')
                ->get(['id', 'member_no', 'full_name', 'phone']),
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if ($request->hasFile('photo')) {
            return $request->file('photo')->store(config('gym.members.photo_path'), config('gym.members.photo_disk'));
        }

        $capturedPhoto = (string) $request->input('captured_photo');

        if (! preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $capturedPhoto)) {
            return null;
        }

        [$meta, $content] = explode(',', $capturedPhoto, 2);
        $extension = str_contains($meta, 'image/png') ? 'png' : 'jpg';
        $path = config('gym.members.photo_path').'/member_'.now()->format('YmdHis').'_'.str()->random(8).'.'.$extension;

        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            return null;
        }

        if (strlen($decoded) > 2 * 1024 * 1024 || @getimagesizefromstring($decoded) === false) {
            return null;
        }

        Storage::disk(config('gym.members.photo_disk'))->put($path, $decoded);

        return $path;
    }
}
