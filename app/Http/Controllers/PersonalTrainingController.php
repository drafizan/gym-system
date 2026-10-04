<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Models\Member;
use App\Models\PtMemberPackage;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\PtTrainer;
use App\Support\Audit;
use App\Support\PtProductCatalog;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonalTrainingController extends Controller
{
    public function trainers(): View
    {
        return view('pt.trainers.index', [
            'trainers' => PtTrainer::query()->latest()->paginate(15),
        ]);
    }

    public function createTrainer(): View
    {
        return view('pt.trainers.create', [
            'trainer' => new PtTrainer([
                'commission_per_session' => 30,
                'joined_at' => now()->toDateString(),
                'status' => RecordStatus::Active->value,
            ]),
        ]);
    }

    public function storeTrainer(Request $request): RedirectResponse
    {
        $trainer = PtTrainer::query()->create($this->validatedTrainer($request));

        Audit::record($request, 'personal_training', 'trainer_created', PtTrainer::class, $trainer->id, null, $trainer->toArray());

        return redirect()->route('pt.trainers.index')->with('success', 'Trainer created successfully.');
    }

    public function editTrainer(PtTrainer $trainer): View
    {
        return view('pt.trainers.edit', compact('trainer'));
    }

    public function updateTrainer(Request $request, PtTrainer $trainer): RedirectResponse
    {
        $oldValues = $trainer->toArray();
        $trainer->update($this->validatedTrainer($request));

        Audit::record($request, 'personal_training', 'trainer_updated', PtTrainer::class, $trainer->id, $oldValues, $trainer->fresh()->toArray());

        return redirect()->route('pt.trainers.index')->with('success', 'Trainer updated successfully.');
    }

    public function packages(): View
    {
        return view('pt.packages.index', [
            'packages' => PtPackage::query()->latest()->paginate(15),
        ]);
    }

    public function createPackage(): View
    {
        return view('pt.packages.create', [
            'package' => new PtPackage([
                'sessions_count' => 3,
                'commission_per_session' => 30,
                'status' => RecordStatus::Active->value,
            ]),
        ]);
    }

    public function storePackage(Request $request, PtProductCatalog $ptProducts): RedirectResponse
    {
        $package = PtPackage::query()->create($this->validatedPackage($request));
        $ptProducts->syncPackage($package);

        Audit::record($request, 'personal_training', 'package_created', PtPackage::class, $package->id, null, $package->toArray());

        return redirect()->route('pt.packages.index')->with('success', 'PT package created successfully.');
    }

    public function editPackage(PtPackage $package): View
    {
        return view('pt.packages.edit', compact('package'));
    }

    public function updatePackage(Request $request, PtPackage $package, PtProductCatalog $ptProducts): RedirectResponse
    {
        $oldValues = $package->toArray();
        $package->update($this->validatedPackage($request, $package));
        $ptProducts->syncPackage($package->fresh());

        Audit::record($request, 'personal_training', 'package_updated', PtPackage::class, $package->id, $oldValues, $package->fresh()->toArray());

        return redirect()->route('pt.packages.index')->with('success', 'PT package updated successfully.');
    }

    public function createMemberPackage(): View
    {
        return view('pt.member-packages.create', [
            'members' => Member::query()
                ->where('status', RecordStatus::Active->value)
                ->whereHas('memberships', fn ($query) => $query
                    ->where('status', MembershipStatus::Active->value)
                    ->whereDate('start_date', '<=', today()->toDateString())
                    ->whereDate('end_date', '>=', today()->toDateString()))
                ->orderBy('full_name')->get(['id', 'member_no', 'full_name', 'phone']),
            'packages' => PtPackage::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
            'paymentMethods' => app(SystemSettings::class)->paymentMethods(),
        ]);
    }

    public function storeMemberPackage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'pt_package_id' => ['required', 'exists:pt_packages,id'],
            'purchased_at' => ['required', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = Member::query()->findOrFail($validated['member_id']);
        $package = PtPackage::query()->findOrFail($validated['pt_package_id']);

        $this->ensureMemberHasActiveMembership($member);

        abort_unless($request->user()->hasPermission('sales.manage'), 403);
        abort_unless($package->status === RecordStatus::Active->value, 422, 'Select an active PT package.');
        $request->validate(['payment_method' => ['required', Rule::in(app(SystemSettings::class)->paymentMethods())]]);
        $product = app(PtProductCatalog::class)->syncPackage($package);
        $token = (string) Str::uuid();
        $request->session()->put('registration_checkouts.'.$token, [
            'member_id' => $member->id,
            'sale_type' => 'pt_session',
            'pt_package_id' => $package->id,
            'pt_price' => $validated['price'] ?? $package->price,
            'purchased_at' => $validated['purchased_at'],
            'notes' => $validated['notes'] ?? null,
            'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => $request->input('payment_method'),
            'registration_fee' => 0,
            'registration_checkout_token' => $token,
        ]);

        return redirect()->route('sales.pos', ['registration_checkout' => $token])
            ->with('success', 'Review payment in POS. PT sessions are assigned only after payment is completed.');
    }

    public function schedule(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();
        $trainerId = $request->query('trainer_id');

        return view('pt.schedule.index', [
            'sessions' => PtSession::query()
                ->with(['trainer', 'member', 'memberPackage.package'])
                ->whereDate('session_date', $date)
                ->when($trainerId, fn ($query) => $query->where('trainer_id', $trainerId))
                ->orderBy('scheduled_start_at')
                ->orderBy('session_date')
                ->get(),
            'trainers' => PtTrainer::query()->orderBy('name')->get(),
            'date' => $date,
            'trainerId' => $trainerId,
        ]);
    }

    public function createSchedule(): View
    {
        return view('pt.schedule.create', [
            'trainers' => PtTrainer::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
            'memberPackages' => $this->activeMemberPackages(),
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'check_in' => ['sometimes', 'boolean'],
            'check_in_token' => ['required_if:check_in,1', 'nullable', 'uuid'],
            'pt_member_package_id' => ['nullable', 'exists:pt_member_packages,id'],
            'pt_member_package_ids' => ['nullable', 'array', 'max:20'],
            'pt_member_package_ids.*' => ['integer', 'exists:pt_member_packages,id'],
            'trainer_id' => ['required', 'exists:pt_trainers,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $memberPackageIds = collect($validated['pt_member_package_ids'] ?? [])
            ->push($validated['pt_member_package_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($memberPackageIds->isEmpty()) {
            throw ValidationException::withMessages([
                'pt_member_package_ids' => 'Add at least one eligible member to the schedule.',
            ]);
        }

        $scheduleDate = DB::transaction(function () use ($request, $validated, $memberPackageIds): string {
            $checkIn = $request->boolean('check_in');
            if ($checkIn && Carbon::parse($validated['session_date'])->isAfter(today())) {
                throw ValidationException::withMessages(['session_date' => 'Check-in cannot be recorded for a future date.']);
            }
            $trainer = PtTrainer::query()->findOrFail($validated['trainer_id']);
            $startAt = Carbon::parse($validated['session_date'].' '.$validated['start_time']);
            $endAt = $startAt->copy()->addMinutes((int) $validated['duration_minutes']);
            $memberPackages = PtMemberPackage::query()
                ->with('package')
                ->whereKey($memberPackageIds)
                ->lockForUpdate()
                ->get();

            if ($memberPackages->count() !== $memberPackageIds->count()) {
                throw ValidationException::withMessages([
                    'pt_member_package_ids' => 'One or more selected PT packages could not be found.',
                ]);
            }

            if ($checkIn && PtSession::query()->where('check_in_token', $validated['check_in_token'])->exists()) {
                return $startAt->toDateString();
            }

            $memberPackages->each(fn (PtMemberPackage $memberPackage) => $this->ensureSchedulable($memberPackage, $trainer));
            if (! $checkIn) {
                $this->ensureTrainerIsAvailable($trainer, $startAt, $endAt);
            }

            $memberPackages->each(function (PtMemberPackage $memberPackage) use ($request, $validated, $trainer, $startAt, $endAt, $checkIn): void {
                $session = PtSession::query()->create([
                    'check_in_token' => $checkIn ? $validated['check_in_token'] : null,
                    'pt_member_package_id' => $memberPackage->id,
                    'trainer_id' => $trainer->id,
                    'member_id' => $memberPackage->member_id,
                    'session_date' => $startAt->toDateString(),
                    'scheduled_start_at' => $startAt,
                    'scheduled_end_at' => $endAt,
                    'duration_minutes' => $validated['duration_minutes'],
                    'status' => $checkIn ? 'completed' : 'scheduled',
                    'commission_amount' => $checkIn ? (float) ($memberPackage->package?->commission_per_session ?? $trainer->commission_per_session) : 0,
                    'notes' => $validated['notes'] ?? null,
                    'recorded_by' => $request->user()?->id,
                ]);

                if ($checkIn) {
                    $memberPackage->increment('used_sessions');
                    if ($memberPackage->fresh()->remainingSessions() <= 0) {
                        $memberPackage->update(['status' => 'completed']);
                    }
                }

                Audit::record($request, 'personal_training', $checkIn ? 'session_checked_in' : 'session_scheduled', PtSession::class, $session->id, null, $session->toArray());
            });

            return $startAt->toDateString();
        });

        return redirect()
            ->route('pt.schedule.index', ['date' => $scheduleDate])
            ->with('success', ($request->boolean('check_in') ? 'PT check-in recorded and one session deducted for ' : 'PT schedule saved for ').$memberPackageIds->count().' member'.($memberPackageIds->count() === 1 ? '' : 's').'.');
    }

    public function completeScheduledSession(Request $request, PtSession $session): RedirectResponse
    {
        DB::transaction(function () use ($request, $session): void {
            $session = PtSession::query()->with(['memberPackage.package', 'trainer'])->lockForUpdate()->findOrFail($session->id);

            if (! $session->isScheduled()) {
                throw ValidationException::withMessages([
                    'session' => 'Only scheduled sessions can be completed from the schedule.',
                ]);
            }

            $memberPackage = $session->memberPackage;
            $trainer = $session->trainer;
            $this->ensureSchedulable($memberPackage, $trainer);

            $oldValues = $session->toArray();
            $commission = (float) ($memberPackage->package?->commission_per_session ?? $trainer->commission_per_session);

            $session->update([
                'status' => 'completed',
                'commission_amount' => $commission,
                'recorded_by' => $request->user()?->id,
            ]);

            $memberPackage->increment('used_sessions');
            if ($memberPackage->fresh()->remainingSessions() <= 0) {
                $memberPackage->update(['status' => 'completed']);
            }

            Audit::record($request, 'personal_training', 'scheduled_session_completed', PtSession::class, $session->id, $oldValues, $session->fresh()->toArray());
        });

        return back()->with('success', 'Scheduled PT session completed.');
    }

    public function cancelScheduledSession(Request $request, PtSession $session): RedirectResponse
    {
        if (! $session->isScheduled()) {
            throw ValidationException::withMessages([
                'session' => 'Only scheduled sessions can be cancelled.',
            ]);
        }

        $oldValues = $session->toArray();
        $session->update([
            'status' => 'cancelled',
            'commission_amount' => 0,
            'recorded_by' => $request->user()?->id,
        ]);

        Audit::record($request, 'personal_training', 'scheduled_session_cancelled', PtSession::class, $session->id, $oldValues, $session->fresh()->toArray());

        return back()->with('success', 'Scheduled PT session cancelled.');
    }

    public function sessions(): View
    {
        return view('pt.sessions.index', [
            'sessions' => PtSession::query()
                ->with(['trainer', 'member', 'memberPackage.package'])
                ->latest('session_date')
                ->paginate(15),
            'balances' => PtMemberPackage::query()
                ->with(['member', 'package'])
                ->where('status', RecordStatus::Active->value)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function createSession(): View
    {
        return view('pt.sessions.create', [
            'trainers' => PtTrainer::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
            'memberPackages' => $this->activeMemberPackages(),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pt_member_package_id' => ['required', 'exists:pt_member_packages,id'],
            'trainer_id' => ['required', 'exists:pt_trainers,id'],
            'session_date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'status' => ['required', Rule::in(['completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = DB::transaction(function () use ($request, $validated): PtSession {
            $memberPackage = PtMemberPackage::query()->with('package')->lockForUpdate()->findOrFail($validated['pt_member_package_id']);
            $trainer = PtTrainer::query()->findOrFail($validated['trainer_id']);

            if (! $memberPackage->isActive()) {
                throw ValidationException::withMessages([
                    'pt_member_package_id' => 'This PT package has no usable sessions left.',
                ]);
            }

            if (! $trainer->isActive()) {
                throw ValidationException::withMessages([
                    'trainer_id' => 'Inactive trainers cannot receive new sessions.',
                ]);
            }

            $commission = $validated['status'] === 'completed'
                ? (float) ($memberPackage->package?->commission_per_session ?? $trainer->commission_per_session)
                : 0.0;

            $session = PtSession::query()->create([
                'pt_member_package_id' => $memberPackage->id,
                'trainer_id' => $trainer->id,
                'member_id' => $memberPackage->member_id,
                'session_date' => Carbon::parse($validated['session_date'])->toDateString(),
                'duration_minutes' => $validated['duration_minutes'],
                'status' => $validated['status'],
                'commission_amount' => $commission,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()?->id,
            ]);

            if ($session->isCompleted()) {
                $memberPackage->increment('used_sessions');
                if ($memberPackage->fresh()->remainingSessions() <= 0) {
                    $memberPackage->update(['status' => 'completed']);
                }
            }

            Audit::record($request, 'personal_training', 'session_recorded', PtSession::class, $session->id, null, $session->toArray());

            return $session;
        });

        return redirect()->route('pt.sessions.index')->with('success', 'PT session recorded successfully.');
    }

    public function commissionReport(Request $request): View
    {
        $dateFrom = Carbon::parse($request->query('date_from', now()->startOfMonth()->toDateString()))->toDateString();
        $dateTo = Carbon::parse($request->query('date_to', now()->toDateString()))->toDateString();
        $trainerId = $request->query('trainer_id');

        $sessions = PtSession::query()
            ->with(['trainer', 'member'])
            ->where('status', 'completed')
            ->whereBetween('session_date', [$dateFrom, $dateTo])
            ->when($trainerId, fn ($query) => $query->where('trainer_id', $trainerId))
            ->orderBy('session_date')
            ->get();

        $summary = $sessions
            ->groupBy('trainer_id')
            ->map(fn ($sessions): array => [
                'trainer' => $sessions->first()->trainer,
                'sessions' => $sessions->count(),
                'commission' => $sessions->sum(fn (PtSession $session): float => (float) $session->commission_amount),
            ])
            ->values();

        return view('pt.reports.commission', [
            'trainers' => PtTrainer::query()->orderBy('name')->get(),
            'sessions' => $sessions,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'trainerId' => $trainerId,
        ]);
    }

    private function validatedTrainer(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'specialization' => ['nullable', 'string', 'max:160'],
            'commission_per_session' => ['required', 'numeric', 'min:0', 'max:9999'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function validatedPackage(Request $request, ?PtPackage $package = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('pt_packages', 'name')->ignore($package)],
            'sessions_count' => ['required', 'integer', 'min:1', 'max:999'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'commission_per_session' => ['required', 'numeric', 'min:0', 'max:9999'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureMemberHasActiveMembership(Member $member): void
    {
        $hasActiveMembership = $member->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->exists();

        if ($member->status !== RecordStatus::Active->value || ! $hasActiveMembership) {
            throw ValidationException::withMessages([
                'member_id' => 'An active membership is required before buying PT packages.',
            ]);
        }
    }

    private function activeMemberPackages()
    {
        return PtMemberPackage::query()
            ->with(['member', 'package'])
            ->where('status', RecordStatus::Active->value)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (PtMemberPackage $package): bool => $package->isActive())
            ->values();
    }

    private function ensureSchedulable(PtMemberPackage $memberPackage, PtTrainer $trainer): void
    {
        if (! $memberPackage->isActive()) {
            throw ValidationException::withMessages([
                'pt_member_package_id' => 'This PT package has no usable sessions left.',
            ]);
        }

        if (! $trainer->isActive()) {
            throw ValidationException::withMessages([
                'trainer_id' => 'Inactive trainers cannot receive new sessions.',
            ]);
        }
    }

    private function ensureTrainerIsAvailable(PtTrainer $trainer, Carbon $startAt, Carbon $endAt): void
    {
        $hasConflict = PtSession::query()
            ->where('trainer_id', $trainer->id)
            ->whereIn('status', ['scheduled', 'completed'])
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<', $endAt)
            ->where('scheduled_end_at', '>', $startAt)
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'start_time' => 'This trainer already has a PT session during the selected time.',
            ]);
        }
    }
}
