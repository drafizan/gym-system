<div class="form-grid">
    <label class="field">
        <span>SKU</span>
        <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="Example: DRK-WATER-500" required>
    </label>

    <label class="field">
        <span>Product Name</span>
        <input type="text" name="name" value="{{ old('name', $product->name) }}" placeholder="Enter product name" required>
    </label>

    <label class="field">
        <span>Category</span>
        <select name="product_category_id">
            <option value="">No category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product->product_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="field">
        <span>Selling Price (RM)</span>
        <input type="number" name="selling_price" min="0" step="0.01" value="{{ old('selling_price', $product->selling_price ?? 0) }}" required>
    </label>

    <label class="field">
        <span>Stock Quantity</span>
        <input type="number" name="stock_quantity" min="0" step="1" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required>
    </label>

    <label class="field">
        <span>Reorder Level</span>
        <input type="number" name="reorder_level" min="0" step="1" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}" required>
    </label>

    <label class="field">
        <span>Status</span>
        <select name="status" required>
            <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Inactive</option>
        </select>
    </label>

    <label class="field form-span-2">
        <span>Description</span>
        <textarea name="description" rows="4" placeholder="Optional">{{ old('description', $product->description) }}</textarea>
    </label>
</div>
