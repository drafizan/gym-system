<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use App\Models\BackupLog;
use App\Models\Member;
use App\Models\MembershipPackage;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Audit;
use App\Support\BackupManager;
use App\Support\PtProductCatalog;
use App\Support\RegistrationCheckout;
use App\Support\SalesManager;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function create(Request $request, PtProductCatalog $ptProducts): View|RedirectResponse
    {
        $request->validate(['registration_checkout' => ['nullable', 'uuid']]);
        $draft = RegistrationCheckout::draft($request, $request->query('registration_checkout'));
        if (! empty($draft['sale_id'])) {
            return redirect()->route('sales.receipt', $draft['sale_id']);
        }
        $ptProducts->syncActivePackages();

        return view('sales.pos', [
            'checkout' => $draft,
            'members' => Member::query()->orderBy('full_name')->get(['id', 'member_no', 'full_name', 'phone']),
            'packages' => MembershipPackage::query()->where('status', 'active')->where('name', '!=', 'Registration Fee')->orderBy('name')->get(),
            'products' => Product::query()->with('category')->where('status', 'active')->orderBy('name')->get(),
            'paymentMethods' => app(SystemSettings::class)->paymentMethods(),
            'saleTypes' => [
                SaleType::MembershipSale,
                SaleType::MembershipRenewal,
                SaleType::ProductSale,
                SaleType::PtSession,
            ],
        ]);
    }

    public function store(Request $request, SalesManager $sales): RedirectResponse
    {
        $request->validate(['registration_checkout_token' => ['nullable', 'uuid']]);
        $token = $request->input('registration_checkout_token');
        $draft = RegistrationCheckout::draft($request, $token);
        if (! empty($draft['sale_id'])) {
            return redirect()->route('sales.receipt', $draft['sale_id']);
        }
        if ($draft) {
            $request->merge(collect($draft)->except(['payment_method', 'sale_id'])->all());
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'membership_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_type' => ['required', Rule::in(array_map(fn (SaleType $type): string => $type->value, SaleType::cases()))],
            'member_id' => ['nullable', 'exists:members,id'],
            'membership_package_id' => ['nullable', 'exists:membership_packages,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'product_items' => ['nullable', 'array', 'max:50'],
            'product_items.*.product_id' => ['nullable', 'exists:products,id'],
            'product_items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'product_items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $draft) {
            unset($validated['start_date'], $validated['end_date'], $validated['membership_amount']);
        }

        $productItems = collect($validated['product_items'] ?? [])
            ->filter(fn (array $item): bool => ! empty($item['product_id']))
            ->map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'] ?? 1,
                'discount' => $item['discount'] ?? 0,
            ])
            ->values()
            ->all();

        if ($productItems === [] && ! empty($validated['product_id'])) {
            $productItems[] = [
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'] ?? 1,
            ];
        }

        if (collect($productItems)->pluck('product_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'product_items' => 'Each product can only be selected once per sale.',
            ]);
        }

        $sale = $sales->complete($request, [
            ...$validated,
            'product_items' => $productItems,
            ...($draft ? ['registration_fee' => $draft['registration_fee'], 'registration_checkout_token' => $token] : []),
        ]);

        if ($draft) {
            $request->session()->put('registration_checkouts.'.$token.'.sale_id', $sale->id);
        }

        return redirect()->route('sales.receipt', $sale)->with('success', 'Sale completed successfully.');
    }

    public function endOfDay(Request $request, BackupManager $backups): RedirectResponse
    {
        Audit::record($request, 'backup', 'end_of_day_started', BackupLog::class, null, null, [
            'backup_type' => 'end_of_day',
        ]);

        $log = $backups->run('end_of_day', $request->user());

        Audit::record($request, 'backup', $log->status === 'completed' ? 'end_of_day_completed' : 'end_of_day_failed', BackupLog::class, $log->id, null, [
            'backup_type' => $log->backup_type,
            'status' => $log->status,
            'filename' => $log->filename,
            'file_size' => $log->file_size,
            'error_message' => $log->error_message,
        ]);

        if ($log->status === 'completed') {
            return back()->with('success', 'End of day completed. Daily backup has been created.');
        }

        return back()->with('error', 'End of day backup failed: '.$log->error_message);
    }

    public function history(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $sales = Sale::query()
            ->with(['member', 'cashier', 'items', 'payments'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('receipt_no', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($query) => $query->where('full_name', 'like', "%{$search}%"));
            }))
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString();

        return view('sales.history', [
            'sales' => $sales,
            'search' => $search,
        ]);
    }

    public function receipt(Sale $sale): View
    {
        return view('sales.receipt', [
            'sale' => $sale->load(['member', 'cashier', 'items', 'payments']),
        ]);
    }
}
