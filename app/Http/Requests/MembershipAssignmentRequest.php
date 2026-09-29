<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipAssignmentRequest extends FormRequest
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
            'membership_package_id' => ['required', 'exists:membership_packages,id'],
            'start_date' => ['required', 'date'],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
