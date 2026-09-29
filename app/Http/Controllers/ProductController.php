<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Http\Requests\ProductCategoryRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Support\ProductCatalogManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $categoryId = $request->query('category');

        $products = Product::query()
            ->with('category')
            ->search($search)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($categoryId, fn ($query) => $query->where('product_category_id', $categoryId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => ProductCategory::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
            'categoryId' => $categoryId,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product([
                'status' => RecordStatus::Active->value,
                'stock_quantity' => 0,
                'reorder_level' => 0,
                'selling_price' => 0,
            ]),
            'categories' => ProductCategory::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request, ProductCatalogManager $manager): RedirectResponse
    {
        $product = $manager->createProduct($request, $request->validated());

        return redirect()->route('products.edit', $product)->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product->load('category'),
            'categories' => ProductCategory::query()->where('status', RecordStatus::Active->value)->orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductCatalogManager $manager): RedirectResponse
    {
        $manager->updateProduct($request, $product, $request->validated());

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function categories(): View
    {
        return view('products.categories.index', [
            'categories' => ProductCategory::query()->withCount('products')->orderBy('name')->paginate(15),
        ]);
    }

    public function createCategory(): View
    {
        return view('products.categories.create', [
            'productCategory' => new ProductCategory([
                'status' => RecordStatus::Active->value,
            ]),
        ]);
    }

    public function storeCategory(ProductCategoryRequest $request, ProductCatalogManager $manager): RedirectResponse
    {
        $manager->createCategory($request, $request->validated());

        return redirect()->route('product-categories.index')->with('success', 'Category created successfully.');
    }

    public function editCategory(ProductCategory $productCategory): View
    {
        return view('products.categories.edit', [
            'productCategory' => $productCategory,
        ]);
    }

    public function updateCategory(ProductCategoryRequest $request, ProductCategory $productCategory, ProductCatalogManager $manager): RedirectResponse
    {
        $manager->updateCategory($request, $productCategory, $request->validated());

        return redirect()->route('product-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(Request $request, ProductCategory $productCategory, ProductCatalogManager $manager): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return redirect()
                ->route('product-categories.index')
                ->with('error', 'Category cannot be deleted because products are still assigned to it.');
        }

        $manager->deleteCategory($request, $productCategory);

        return redirect()->route('product-categories.index')->with('success', 'Category deleted successfully.');
    }

    public function lowStock(): View
    {
        return view('products.low-stock', [
            'products' => Product::query()
                ->with('category')
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->orderBy('stock_quantity')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function priceChanges(): View
    {
        return view('products.price-changes', [
            'priceChanges' => ProductPriceHistory::query()
                ->with(['product', 'changedBy'])
                ->latest('changed_at')
                ->paginate(15),
        ]);
    }
}
