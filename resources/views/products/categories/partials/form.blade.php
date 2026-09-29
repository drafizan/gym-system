<div class="form-grid">
    <label class="field">
        <span>Category Name</span>
        <input type="text" name="name" value="{{ old('name', $productCategory->name) }}" placeholder="Enter category name" required>
    </label>

    <label class="field">
        <span>Status</span>
        <select name="status" required>
            <option value="active" @selected(old('status', $productCategory->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $productCategory->status) === 'inactive')>Inactive</option>
        </select>
    </label>

    <label class="field form-span-2">
        <span>Description</span>
        <textarea name="description" rows="4" placeholder="Optional">{{ old('description', $productCategory->description) }}</textarea>
    </label>
</div>
