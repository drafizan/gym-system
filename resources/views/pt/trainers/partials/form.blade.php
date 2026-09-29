<div class="form-grid two-columns">
    <label class="form-row">
        <span>Trainer Name</span>
        <input type="text" name="name" value="{{ old('name', $trainer->name) }}" required>
    </label>
    <label class="form-row">
        <span>Phone</span>
        <input type="text" name="phone" value="{{ old('phone', $trainer->phone) }}">
    </label>
    <label class="form-row">
        <span>Email</span>
        <input type="email" name="email" value="{{ old('email', $trainer->email) }}">
    </label>
    <label class="form-row">
        <span>Specialization</span>
        <input type="text" name="specialization" value="{{ old('specialization', $trainer->specialization) }}" placeholder="Example: Strength, weight loss, rehab">
    </label>
    <label class="form-row">
        <span>Commission Per Session (RM)</span>
        <input type="number" name="commission_per_session" min="0" step="0.01" value="{{ old('commission_per_session', $trainer->commission_per_session ?? 30) }}" required>
    </label>
    <label class="form-row">
        <span>Joined Date</span>
        <input type="date" name="joined_at" value="{{ old('joined_at', optional($trainer->joined_at)->format('Y-m-d') ?? now()->toDateString()) }}">
    </label>
    <label class="form-row">
        <span>Status</span>
        <select name="status" required>
            <option value="active" @selected(old('status', $trainer->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $trainer->status) === 'inactive')>Inactive</option>
        </select>
    </label>
    <label class="form-row full-width">
        <span>Notes</span>
        <textarea name="notes" rows="4" placeholder="Optional">{{ old('notes', $trainer->notes) }}</textarea>
    </label>
</div>
