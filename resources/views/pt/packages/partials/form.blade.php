<div class="form-grid two-columns">
    <label class="form-row">
        <span>Package Name</span>
        <input type="text" name="name" value="{{ old('name', $package->name) }}" required>
    </label>
    <label class="form-row">
        <span>Sessions</span>
        <input type="number" name="sessions_count" min="1" step="1" value="{{ old('sessions_count', $package->sessions_count ?? 3) }}" required>
    </label>
    <label class="form-row">
        <span>Price (RM)</span>
        <input type="number" name="price" min="0" step="0.01" value="{{ old('price', $package->price ?? 0) }}" required>
    </label>
    <label class="form-row">
        <span>Commission Per Session (RM)</span>
        <input type="number" name="commission_per_session" min="0" step="0.01" value="{{ old('commission_per_session', $package->commission_per_session ?? 30) }}" required>
    </label>
    <label class="form-row">
        <span>Validity Days</span>
        <input type="number" name="validity_days" min="1" step="1" value="{{ old('validity_days', $package->validity_days) }}" placeholder="No expiry">
    </label>
    <label class="form-row">
        <span>Status</span>
        <select name="status" required>
            <option value="active" @selected(old('status', $package->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $package->status) === 'inactive')>Inactive</option>
        </select>
    </label>
    <label class="form-row full-width">
        <span>Description</span>
        <textarea name="description" rows="4" placeholder="Optional">{{ old('description', $package->description) }}</textarea>
    </label>
</div>
