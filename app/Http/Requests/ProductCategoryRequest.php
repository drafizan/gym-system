<?php

namespace App\Http\Requests;

use App\Enums\RecordStatus;
use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('product_categories', 'name')->ignore($this->category())],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
        ];
    }

    private function category(): ?ProductCategory
    {
        $category = $this->route('productCategory');

        return $category instanceof ProductCategory ? $category : null;
    }
}
