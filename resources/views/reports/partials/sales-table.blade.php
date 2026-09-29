<div class="table-responsive sales-table">
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Receipt No.</th>
                <th>Type</th>
                <th>Description</th>
                <th>Payment Method</th>
                <th>Amount (RM)</th>
                <th>Received By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                @php
                    $salesCategory = $transaction['category'] ?? (
                        str_contains($transaction['type'], 'membership')
                            ? 'membership'
                            : ($transaction['type'] === 'product_sale' ? 'product' : 'other')
                    );
                    $typeLabel = $transaction['type_label'] ?? str($transaction['type'])->headline()->toString();
                    $typeTone = match ($salesCategory) {
                        'membership' => 'success',
                        'pt' => 'info',
                        'product' => 'purple',
                        default => 'warning',
                    };
                @endphp
                <tr @isset($filterable) data-sales-category="{{ $salesCategory }}" @endisset>
                    <td>{{ $transaction['time'] }}</td>
                    <td>{{ $transaction['receipt_no'] }}</td>
                    <td><span class="sale-type-pill {{ $typeTone }}">{{ $typeLabel }}</span></td>
                    <td>{{ $transaction['description'] }}</td>
                    <td>{{ \App\Enums\PaymentMethod::labelFor($transaction['payment_method']) }}</td>
                    <td>{{ number_format((float) $transaction['amount'], 2) }}</td>
                    <td>{{ $transaction['received_by'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No transactions found.</td></tr>
            @endforelse
            @isset($filterable)
                <tr class="sales-filter-empty" hidden><td colspan="7">No transactions found for this filter.</td></tr>
            @endisset
        </tbody>
    </table>
</div>
