<?php

namespace App\Support;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PtPackage;

class PtProductCatalog
{
    public function syncActivePackages(): void
    {
        PtPackage::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('sessions_count')
            ->get()
            ->each(fn (PtPackage $package) => $this->syncPackage($package));
    }

    public function syncPackage(PtPackage $package): Product
    {
        $category = ProductCategory::query()->firstOrCreate([
            'name' => 'Services',
        ], [
            'description' => 'Service packages sold through POS.',
            'status' => RecordStatus::Active->value,
        ]);

        return Product::query()->updateOrCreate([
            'sku' => $this->skuForPackage($package),
        ], [
            'product_category_id' => $category->id,
            'name' => $package->name,
            'description' => 'Personal training package.',
            'selling_price' => $package->price,
            'stock_quantity' => 9999,
            'reorder_level' => 0,
            'status' => $package->status,
        ]);
    }

    public function isPersonalTrainingProduct(Product $product): bool
    {
        return str_starts_with((string) $product->sku, 'SRV-PT-')
            || (
                strcasecmp((string) $product->category?->name, 'Services') === 0
                && str_contains(strtolower($product->name), 'pt session')
            );
    }

    private function skuForPackage(PtPackage $package): string
    {
        return 'SRV-PT-'.$package->sessions_count;
    }
}
