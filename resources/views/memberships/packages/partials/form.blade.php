<div class="form-grid">
    <label class="field">
        <span>Package Name</span>
        <input type="text" name="name" value="{{ old('name', $membershipPackage->name) }}" required>
    </label>

    <label class="field">
        <span>Duration Days</span>
        <input type="number" name="duration_days" min="1" value="{{ old('duration_days', $membershipPackage->duration_days) }}" required>
    </label>

    <label class="field">
        <span>Price (RM)</span>
        <input type="number" name="price" min="0" step="0.01" value="{{ old('price', $membershipPackage->price ?? 0) }}" required>
    </label>

    <label class="field">
        <span>Status</span>
        <select name="status" required>
            @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $membershipPackage->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="check-field">
        <input type="checkbox" name="access_allowed" value="1" @checked(old('access_allowed', $membershipPackage->access_allowed ?? true))>
        <span>Allow door access</span>
    </label>
</div>
