<?php

namespace App\Support;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCatalogManager
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(Request $request, array $data): ProductCategory
    {
        $category = ProductCategory::query()->create([
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? RecordStatus::Active->value,
        ]);

        Audit::record($request, 'products', 'category_created', ProductCategory::class, $category->id, null, $category->toArray());

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(Request $request, ProductCategory $category, array $data): ProductCategory
    {
        $oldValues = $category->toArray();

        $category->update([
            'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $category->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
            'status' => $data['status'] ?? $category->status,
        ]);

        Audit::record($request, 'products', 'category_updated', ProductCategory::class, $category->id, $oldValues, $category->fresh()->toArray());

        return $category->fresh();
    }

    public function deleteCategory(Request $request, ProductCategory $category): void
    {
        $oldValues = $category->toArray();
        $categoryId = $category->id;

        $category->delete();

        Audit::record($request, 'products', 'category_deleted', ProductCategory::class, $categoryId, $oldValues, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(Request $request, array $data): Product
    {
        return DB::transaction(function () use ($request, $data): Product {
            $product = Product::query()->create([
                'product_category_id' => $data['product_category_id'] ?? null,
                'sku' => trim((string) $data['sku']),
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'selling_price' => $data['selling_price'] ?? 0,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'reorder_level' => $data['reorder_level'] ?? 0,
                'status' => $data['status'] ?? RecordStatus::Active->value,
            ]);

            Audit::record($request, 'products', 'created', Product::class, $product->id, null, $product->toArray());

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Request $request, Product $product, array $data): Product
    {
        return DB::transaction(function () use ($request, $product, $data): Product {
            $oldValues = $product->toArray();
            $oldPrice = (string) $product->selling_price;

            $product->update([
                'product_category_id' => array_key_exists('product_category_id', $data) ? $data['product_category_id'] : $product->product_category_id,
                'sku' => array_key_exists('sku', $data) ? trim((string) $data['sku']) : $product->sku,
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $product->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
                'selling_price' => $data['selling_price'] ?? $product->selling_price,
                'stock_quantity' => $data['stock_quantity'] ?? $product->stock_quantity,
                'reorder_level' => $data['reorder_level'] ?? $product->reorder_level,
                'status' => $data['status'] ?? $product->status,
            ]);

            if (array_key_exists('selling_price', $data) && number_format((float) $oldPrice, 2, '.', '') !== number_format((float) $product->selling_price, 2, '.', '')) {
                ProductPriceHistory::query()->create([
                    'product_id' => $product->id,
                    'old_price' => $oldPrice,
                    'new_price' => $product->selling_price,
                    'changed_by' => $request->user()?->id,
                    'changed_at' => now(),
                ]);
            }

            Audit::record($request, 'products', 'updated', Product::class, $product->id, $oldValues, $product->fresh()->toArray());

            return $product->fresh();
        });
    }

    public function findSellableProductBySku(string $sku): Product
    {
        $product = Product::query()
            ->where('sku', trim($sku))
            ->firstOrFail();

        if (! $product->isActive()) {
            throw ValidationException::withMessages([
                'product' => 'Inactive products cannot be sold.',
            ]);
        }

        return $product;
    }
}
