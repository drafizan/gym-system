@php
    $cancelUrl ??= route('members.index');
    $submitLabel ??= 'Save Member';
    $showSaveAnother ??= false;
    $membershipPackages ??= collect();
    $paymentMethods ??= \App\Enums\PaymentMethod::values();
    $allowMembershipSetup ??= false;
    $activeReferrers ??= collect();
    $memberPhotoUrl = $member->photo_path ? Storage::disk(config('gym.members.photo_disk'))->url($member->photo_path) : null;
    $currentMembership = $member->latestMembership;
    $selectedPackageId = old('membership_package_id', $currentMembership?->membership_package_id);
    $membershipStartDate = old('membership_start_date', ($member->exists && $allowMembershipSetup) ? now()->format('Y-m-d') : ($currentMembership?->start_date?->format('Y-m-d') ?? now()->format('Y-m-d')));
    $membershipEndDate = old('membership_end_date', ($member->exists && $allowMembershipSetup) ? null : $currentMembership?->end_date?->format('Y-m-d'));
    $membershipAmount = old('membership_amount', $currentMembership?->amount);
    $membershipPaymentMethod = old('membership_payment_method', 'cash');
    $membershipPaymentStatus = old('membership_payment_status', $currentMembership?->payment_status ?? 'paid');
    $canEditMembershipEndDate = $allowMembershipSetup || $currentMembership !== null;
@endphp

<div class="registration-layout">
    <div class="registration-main">
        <section class="registration-card">
            <h2>Personal Information</h2>
            <div class="form-grid compact">
                <label class="field">
                    <span>Full Name <b>*</b></span>
                    <input type="text" name="full_name" value="{{ old('full_name', $member->full_name) }}" placeholder="Enter full name" required>
                </label>

                <label class="field">
                    <span>IC / Passport No.</span>
                    <input type="text" name="ic_passport_no" value="{{ old('ic_passport_no', $member->ic_passport_no) }}" placeholder="Enter IC or passport number">
                </label>

                <label class="field">
                    <span>Date of Birth</span>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}">
                </label>

                <label class="field">
                    <span>Gender</span>
                    <select name="gender">
                        <option value="">Select gender</option>
                        @foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $member->gender) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Phone Number <b>*</b></span>
                    <input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" placeholder="+601128520309" inputmode="tel" pattern="(?:\+?60|0)(?:1[0-46-9][\s-]?\d{3,4}[\s-]?\d{4}|[3-9][\s-]?\d{7,8})" title="Use a Malaysian phone number, for example +601128520309, 011-2852 0309, or +6065252503" required>
                </label>

                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $member->email) }}" placeholder="Enter email address">
                </label>

                <label class="field form-span-2">
                    <span>Address</span>
                    <textarea name="address" rows="3" placeholder="Enter full address">{{ old('address', $member->address) }}</textarea>
                </label>
            </div>
        </section>

        <section class="registration-card">
            <h2>Emergency Contact</h2>
            <div class="form-grid compact">
                <label class="field">
                    <span>Contact Name</span>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $member->emergency_contact_name) }}" placeholder="Enter contact name">
                </label>

                <label class="field">
                    <span>Relationship</span>
                    <input type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $member->emergency_contact_relationship) }}" placeholder="Enter relationship">
                </label>

                <label class="field">
                    <span>Contact Number</span>
                    <input type="tel" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $member->emergency_contact_phone) }}" placeholder="+6065252503" inputmode="tel" pattern="(?:\+?60|0)(?:1[0-46-9][\s-]?\d{3,4}[\s-]?\d{4}|[3-9][\s-]?\d{7,8})" title="Use a Malaysian phone number, for example +601128520309, 011-2852 0309, or +6065252503">
                </label>
            </div>
        </section>

        <section class="registration-card">
            <h2>Additional Information</h2>
            <div class="form-grid compact additional-info-grid">
                <label class="field join-date-field">
                    <span>Join Date</span>
                    <div class="readonly-date">
                        <strong>{{ now()->format('d/m/Y') }}</strong>
                        <small>Auto generated when member is saved</small>
                    </div>
                </label>

                <label class="field referral-field">
                    <span>Referred By</span>
                    @php
                        $selectedReferrerId = old('referred_by_member_id', $member->referred_by_member_id);
                        $selectedReferrer = $activeReferrers->firstWhere('id', (int) $selectedReferrerId);
                        $selectedReferrerLabel = $selectedReferrer
                            ? $selectedReferrer->full_name.' · '.$selectedReferrer->member_no
                            : 'Select active member';
                    @endphp
                    <div class="filterable-combobox" data-filterable-combobox>
                        <select name="referred_by_member_id" data-combobox-native tabindex="-1" aria-hidden="true">
                            <option value="" data-filter="select active member">Select active member</option>
                            @foreach ($activeReferrers as $referrer)
                                <option
                                    value="{{ $referrer->id }}"
                                    data-filter="{{ str($referrer->full_name.' '.$referrer->member_no.' '.$referrer->phone)->lower() }}"
                                    @selected((int) old('referred_by_member_id', $member->referred_by_member_id) === $referrer->id)
                                >
                                    {{ $referrer->full_name }} · {{ $referrer->member_no }}{{ $referrer->phone ? ' · '.$referrer->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button class="combobox-trigger" type="button" data-combobox-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-combobox-value>{{ $selectedReferrerLabel }}</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </button>
                        <div class="combobox-panel" data-combobox-panel hidden>
                            <div class="combobox-search">
                                <input type="search" data-combobox-search placeholder="Search name, member no., or phone">
                            </div>
                            <div class="combobox-options" data-combobox-options role="listbox">
                                <button class="combobox-option" type="button" data-combobox-option data-value="" data-label="Select active member" data-filter="select active member" role="option">
                                    Select active member
                                </button>
                                @foreach ($activeReferrers as $referrer)
                                    <button
                                        class="combobox-option"
                                        type="button"
                                        data-combobox-option
                                        data-value="{{ $referrer->id }}"
                                        data-label="{{ $referrer->full_name }} · {{ $referrer->member_no }}"
                                        data-filter="{{ str($referrer->full_name.' '.$referrer->member_no.' '.$referrer->phone)->lower() }}"
                                        role="option"
                                    >
                                        <strong>{{ $referrer->full_name }}</strong>
                                        <small>{{ $referrer->member_no }}{{ $referrer->phone ? ' · '.$referrer->phone : '' }}</small>
                                    </button>
                                @endforeach
                                <p class="combobox-empty" data-combobox-empty hidden>No active member found.</p>
                            </div>
                        </div>
                    </div>
                    <small class="field-help">{{ $activeReferrers->count() }} active {{ $activeReferrers->count() === 1 ? 'member' : 'members' }} available</small>
                </label>

                <div class="registration-actions">
                    <button class="btn btn-primary" type="submit" @if ($member->exists) name="registration_checkout" value="1" @endif>{{ $submitLabel }}</button>
                    @if ($member->exists)
                        <button class="btn btn-light" type="submit" name="save_profile_only" value="1">Save Profile Only</button>
                    @endif
                    @if ($showSaveAnother)
                        <button class="btn btn-light" type="submit" name="save_and_add" value="1">Save & Add Another</button>
                    @endif
                    <a class="btn btn-danger-soft" href="{{ $cancelUrl }}">Cancel</a>
                </div>
            </div>
        </section>
    </div>

    <div class="registration-side">
        <section class="registration-card">
            <h2>Membership Information</h2>
            @if ($member->exists && $currentMembership)
                <p>Current membership: {{ $currentMembership->start_date?->format('Y-m-d') }} to {{ $currentMembership->end_date?->format('Y-m-d') }}. The fields below prepare the next membership payment.</p>
            @endif
            <div class="form-grid compact">
                <label class="field form-span-2">
                    <span>Membership Type</span>
                    <select name="membership_package_id" @required(! $member->exists) data-membership-package-select data-existing-expiry="{{ $member->exists ? $currentMembership?->end_date?->format('Y-m-d') : '' }}" @disabled(! $allowMembershipSetup)>
                        <option value="">Select membership type</option>
                        @foreach ($membershipPackages as $package)
                            <option
                                value="{{ $package->id }}"
                                data-price="{{ $package->price }}"
                                data-duration-days="{{ $package->duration_days }}"
                                @selected((int) $selectedPackageId === $package->id)
                            >
                                {{ $package->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Start Date</span>
                    <input type="date" name="membership_start_date" value="{{ $membershipStartDate }}" data-membership-start-date @disabled(! $allowMembershipSetup)>
                    @if (! $allowMembershipSetup && $currentMembership)
                        <input type="hidden" name="membership_start_date" value="{{ $membershipStartDate }}">
                    @endif
                </label>

                <label class="field">
                    <span>End Date</span>
                    <input type="date" name="membership_end_date" value="{{ $membershipEndDate }}" data-membership-end-date @disabled(! $canEditMembershipEndDate)>
                </label>

                <label class="field">
                    <span>Amount (RM)</span>
                    <input type="number" name="membership_amount" min="0" step="0.01" value="{{ $membershipAmount }}" placeholder="Enter amount" data-membership-amount @disabled(! $allowMembershipSetup)>
                </label>

                @if ($member->exists && ! $allowMembershipSetup)
                <label class="field">
                    <span>Payment Method</span>
                    <select name="membership_payment_method" @disabled(! $allowMembershipSetup)>
                        <option value="">Select payment method</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" @selected($membershipPaymentMethod === $method)>{{ \App\Enums\PaymentMethod::labelFor($method) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Payment Status</span>
                    <select name="membership_payment_status" @disabled(! $allowMembershipSetup)>
                        @foreach (['paid' => 'Paid', 'unpaid' => 'Unpaid'] as $value => $label)
                            <option value="{{ $value }}" @selected($membershipPaymentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @endif
            </div>
        </section>

        @if ($allowMembershipSetup)
            <section class="registration-card" data-registration-pos data-registration-fee="{{ $member->exists ? 0 : $registrationFee }}">
                <h2>POS Summary</h2>
                <div class="form-grid compact">
                    <div class="field"><span>Membership</span><strong data-registration-membership-total>RM {{ number_format((float) $membershipAmount, 2) }}</strong></div>
                    @if (! $member->exists)
                    <div class="field"><span>Registration Fee (one time)</span><strong>RM {{ number_format($registrationFee, 2) }}</strong></div>
                    @endif
                    <label class="field form-span-2">
                        <span>Payment Method</span>
                        <select name="membership_payment_method">
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" @selected($membershipPaymentMethod === $method)>{{ \App\Enums\PaymentMethod::labelFor($method) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="field form-span-2"><span>Total</span><strong data-registration-total>RM {{ number_format((float) $membershipAmount + ($member->exists ? 0 : $registrationFee), 2) }}</strong></div>
                </div>
            </section>
        @endif

        <section class="registration-card">
            <h2>Photo & RFID Card</h2>
            <div class="photo-rfid-grid">
                <div>
                    <span class="field-label">Member Photo</span>
                    <div @class(['camera-panel is-compact', 'has-photo' => $memberPhotoUrl])>
                        <div class="camera-preview-frame">
                            <video data-camera-preview playsinline muted @if ($memberPhotoUrl) hidden @endif></video>
                            <img data-photo-preview alt="Member photo preview" src="{{ $memberPhotoUrl }}" @if (! $memberPhotoUrl) hidden @endif>
                        </div>
                        <canvas data-camera-canvas hidden></canvas>
                        <input type="hidden" name="captured_photo" data-camera-input>
                        <label class="photo-upload-drop">
                            <input type="file" name="photo" accept="image/*" data-photo-upload>
                            <span>Upload photo</span>
                        </label>
                        <div class="camera-actions">
                            <button class="btn btn-light icon-action" type="button" data-camera-start data-tooltip="Start camera" aria-label="Start camera" title="Start camera">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M15 10l4.5-2.5A1 1 0 0 1 21 8.4v7.2a1 1 0 0 1-1.5.9L15 14"></path>
                                    <rect x="3" y="7" width="12" height="10" rx="2"></rect>
                                </svg>
                            </button>
                            <button class="btn btn-primary icon-action" type="button" data-camera-capture data-tooltip="Capture photo" aria-label="Capture photo" title="Capture photo">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M14.5 4l1.4 2H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4.1l1.4-2z"></path>
                                    <circle cx="12" cy="12.5" r="3.2"></circle>
                                </svg>
                            </button>
                        </div>
                        <span data-camera-status class="muted">Camera is off.</span>
                    </div>
                </div>

                <div class="rfid-panel">
                    <span class="field-label">RFID Card</span>
                    <label class="field">
                        <span>Card Number</span>
                        <input type="text" name="rfid_card_number" value="{{ old('rfid_card_number', $member->rfid_card_number) }}" placeholder="Enter card number manually">
                    </label>
                    <p class="field-help">Manual entry only until RFID card management is active.</p>
                </div>
            </div>
        </section>

        <section class="registration-card">
            <h2>Remarks</h2>
            <label class="field">
                <span>Remarks</span>
                <textarea name="remarks" rows="3" placeholder="Internal remarks (optional)">{{ old('remarks', $member->remarks) }}</textarea>
            </label>
        </section>
    </div>
</div>
