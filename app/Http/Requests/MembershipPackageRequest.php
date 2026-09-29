<?php

namespace App\Http\Requests;

use App\Models\MembershipPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('memberships.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('membership_packages', 'name')->ignore($this->package())],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_walk_in' => ['nullable', 'boolean'],
            'access_allowed' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function package(): ?MembershipPackage
    {
        $package = $this->route('membershipPackage');

        return $package instanceof MembershipPackage ? $package : null;
    }
}
