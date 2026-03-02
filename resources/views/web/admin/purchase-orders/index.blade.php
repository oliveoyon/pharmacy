@extends('web.layouts.app')

@php($title = __('app.purchase_orders'))

@section('content')
    <div class="crud-grid wide">
        <section class="panel">
            <h4>{{ __('app.create_purchase_order') }}</h4>
            <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="form-grid">
                @csrf
                <label>{{ __('app.branch') }}</label>
                <select name="branch_id" required>
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>

                <label>{{ __('app.supplier') }}</label>
                <select name="supplier_id" required>
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>

                <label>{{ __('app.order_date') }}</label>
                <input type="date" name="ordered_at" value="{{ now()->toDateString() }}">

                <label>{{ __('app.expected_date') }}</label>
                <input type="date" name="expected_at">

                <label>{{ __('app.notes') }}</label>
                <textarea name="notes"></textarea>

                <div class="line-items" id="po-items">
                    <h5>{{ __('app.items') }}</h5>
                    <div class="line-row">
                        <select name="items[0][product_id]" required>
                            <option value="">{{ __('app.product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.001" name="items[0][ordered_qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[0][unit_cost]" placeholder="{{ __('app.unit_cost') }}">
                        <input type="number" step="0.01" name="items[0][tax_percent]" placeholder="{{ __('app.tax_percent') }}">
                        <input type="number" step="0.01" name="items[0][discount_amount]" placeholder="{{ __('app.discount') }}">
                    </div>
                </div>

                <button type="button" id="add-po-row" class="btn btn-light">{{ __('app.add_line') }}</button>
                <button class="btn btn-primary" type="submit">{{ __('app.save') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.purchase_orders_list') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('app.po_no') }}</th>
                        <th>{{ __('app.branch') }}</th>
                        <th>{{ __('app.supplier') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.total') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>{{ $order->po_no }}</td>
                            <td>{{ $order->branch?->name }}</td>
                            <td>{{ $order->supplier?->name }}</td>
                            <td>{{ $order->status }}</td>
                            <td>{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-light btn-xs js-po-details"
                                    data-po-no="{{ $order->po_no }}"
                                    data-items='@json($order->items->map(fn($item) => ["product" => $item->product?->name, "ordered_qty" => (float) $item->ordered_qty, "received_qty" => (float) $item->received_qty, "unit_cost" => (float) $item->unit_cost, "line_total" => (float) $item->line_total])->values())'
                                >
                                    {{ __('app.view_details') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const wrapper = document.getElementById('po-items');
                const addRowBtn = document.getElementById('add-po-row');
                let idx = 1;
                const productOptions = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->values());

                addRowBtn?.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'line-row';

                    const select = document.createElement('select');
                    select.name = `items[${idx}][product_id]`;
                    select.required = true;
                    select.innerHTML = `<option value="">{{ __('app.product') }}</option>` + productOptions.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');

                    row.innerHTML = `
                        <input type="number" step="0.001" name="items[${idx}][ordered_qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[${idx}][unit_cost]" placeholder="{{ __('app.unit_cost') }}">
                        <input type="number" step="0.01" name="items[${idx}][tax_percent]" placeholder="{{ __('app.tax_percent') }}">
                        <input type="number" step="0.01" name="items[${idx}][discount_amount]" placeholder="{{ __('app.discount') }}">
                    `;
                    row.prepend(select);
                    wrapper.appendChild(row);
                    idx++;
                });

                document.querySelectorAll('.js-po-details').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const poNo = btn.getAttribute('data-po-no') || '';
                        const items = JSON.parse(btn.getAttribute('data-items') || '[]');

                        let html = `<div class="swal-details"><table class="swal-table"><thead><tr><th>{{ __('app.product') }}</th><th>{{ __('app.ordered_qty') }}</th><th>{{ __('app.received_qty') }}</th><th>{{ __('app.unit_cost') }}</th><th>{{ __('app.total') }}</th></tr></thead><tbody>`;
                        if (!items.length) {
                            html += `<tr><td colspan="5">{{ __('app.no_data') }}</td></tr>`;
                        } else {
                            items.forEach((item) => {
                                html += `<tr><td>${item.product ?? '-'}</td><td>${item.ordered_qty}</td><td>${item.received_qty}</td><td>${item.unit_cost}</td><td>${item.line_total}</td></tr>`;
                            });
                        }
                        html += `</tbody></table></div>`;

                        Swal.fire({
                            title: `{{ __('app.po_no') }}: ${poNo}`,
                            html,
                            width: 860,
                            confirmButtonText: @json(__('app.close')),
                        });
                    });
                });
            })();
        </script>
    @endpush
@endsection
