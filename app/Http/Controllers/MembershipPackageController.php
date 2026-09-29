<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipPackageRequest;
use App\Models\MembershipPackage;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipPackageController extends Controller
{
    public function index(): View
    {
        return view('memberships.packages.index', [
            'packages' => MembershipPackage::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('memberships.packages.create', [
            'membershipPackage' => new MembershipPackage([
                'is_walk_in' => false,
                'status' => 'active',
                'access_allowed' => true,
            ]),
        ]);
    }

    public function store(MembershipPackageRequest $request): RedirectResponse
    {
        $package = MembershipPackage::query()->create($this->validatedPackage($request));

        Audit::record($request, 'memberships', 'package_created', MembershipPackage::class, $package->id, null, $package->toArray());

        return redirect()->route('membership-packages.index')->with('success', 'Membership package created successfully.');
    }

    public function edit(MembershipPackage $membershipPackage): View
    {
        return view('memberships.packages.edit', [
            'membershipPackage' => $membershipPackage,
        ]);
    }

    public function update(MembershipPackageRequest $request, MembershipPackage $membershipPackage): RedirectResponse
    {
        $oldValues = $membershipPackage->toArray();
        $membershipPackage->update($this->validatedPackage($request));

        Audit::record($request, 'memberships', 'package_updated', MembershipPackage::class, $membershipPackage->id, $oldValues, $membershipPackage->fresh()->toArray());

        return redirect()->route('membership-packages.index')->with('success', 'Membership package updated successfully.');
    }

    public function destroy(Request $request, MembershipPackage $membershipPackage): RedirectResponse
    {
        if ($membershipPackage->memberships()->exists() || $membershipPackage->saleItems()->exists()) {
            return redirect()
                ->route('membership-packages.index')
                ->with('error', 'Package cannot be deleted because membership or sales records are still using it.');
        }

        $oldValues = $membershipPackage->toArray();
        $packageId = $membershipPackage->id;
        $membershipPackage->delete();

        Audit::record($request, 'memberships', 'package_deleted', MembershipPackage::class, $packageId, $oldValues, null);

        return redirect()->route('membership-packages.index')->with('success', 'Membership package deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPackage(MembershipPackageRequest $request): array
    {
        $validated = $request->validated();
        $validated['is_walk_in'] = $request->route('membershipPackage') instanceof MembershipPackage
            ? $request->route('membershipPackage')->is_walk_in
            : false;
        $validated['access_allowed'] = $request->boolean('access_allowed');

        return $validated;
    }
}
