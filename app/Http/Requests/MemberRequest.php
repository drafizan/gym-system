<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\RecordStatus;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('members.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'ic_passport_no' => ['nullable', 'string', 'max:80', Rule::unique('members', 'ic_passport_no')->ignore($this->member())],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['required', 'string', 'max:40', 'regex:'.$this->phonePattern()],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40', 'regex:'.$this->phonePattern()],
            'rfid_card_number' => ['nullable', 'string', 'max:120', Rule::unique('members', 'rfid_card_number')->ignore($this->member())],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'referred_by_member_id' => [
                'nullable',
                Rule::exists('members', 'id')->where('status', RecordStatus::Active->value),
                Rule::notIn(array_filter([$this->member()?->id])),
            ],
            'membership_package_id' => ['nullable', 'exists:membership_packages,id'],
            'membership_start_date' => ['nullable', 'date'],
            'membership_end_date' => ['nullable', 'date', 'after_or_equal:membership_start_date'],
            'membership_amount' => ['nullable', 'numeric', 'min:0'],
            'membership_payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'membership_payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'captured_photo' => ['nullable', 'string', 'max:3000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Malaysian phone number, for example +601128520309, 011-2852 0309, or +6065252503.',
            'emergency_contact_phone.regex' => 'Enter a valid Malaysian phone number, for example +601128520309, 011-2852 0309, or +6065252503.',
        ];
    }

    private function phonePattern(): string
    {
        return '/^(?:\+?60|0)(?:1[0-46-9][\s-]?\d{3,4}[\s-]?\d{4}|[3-9][\s-]?\d{7,8})$/';
    }

    private function member(): ?Member
    {
        $member = $this->route('member');

        return $member instanceof Member ? $member : null;
    }
}
