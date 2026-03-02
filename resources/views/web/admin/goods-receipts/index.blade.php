@extends('web.layouts.app')

@php($title = __('app.goods_receipts'))

@section('content')
    <div class="crud-grid wide">
        <section class="panel">
            <h4>{{ __('app.create_grn') }}</h4>
            <form method="POST" action="{{ route('admin.goods-receipts.store') }}" class="form-grid">
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

                <label>{{ __('app.purchase_order_optional') }}</label>
                <select name="purchase_order_id">
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($purchaseOrders as $purchaseOrder)
                        <option value="{{ $purchaseOrder->id }}">{{ $purchaseOrder->po_no }}</option>
                    @endforeach
                </select>

                <label>{{ __('app.supplier_invoice_no') }}</label>
                <input name="supplier_invoice_no">

                <label>{{ __('app.received_at') }}</label>
                <input type="datetime-local" name="received_at">

                <label>{{ __('app.notes') }}</label>
                <textarea name="notes"></textarea>

                <div class="line-items" id="grn-items">
                    <h5>{{ __('app.items') }}</h5>
                    <div class="line-row">
                        <select name="items[0][product_id]" required>
                            <option value="">{{ __('app.product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        <input name="items[0][batch_no]" placeholder="{{ __('app.batch_no') }}" required>
                        <input type="date" name="items[0][expiry_date]">
                        <input type="number" step="0.001" name="items[0][received_qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[0][unit_cost]" placeholder="{{ __('app.unit_cost') }}">
                    </div>
                </div>

                <button type="button" id="add-grn-row" class="btn btn-light">{{ __('app.add_line') }}</button>
                <button class="btn btn-primary" type="submit">{{ __('app.save') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.grn_list') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('app.grn_no') }}</th>
                        <th>{{ __('app.branch') }}</th>
                        <th>{{ __('app.supplier') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.total') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->grn_no }}</td>
                            <td>{{ $receipt->branch?->name }}</td>
                            <td>{{ $receipt->supplier?->name }}</td>
                            <td>{{ $receipt->status }}</td>
                            <td>{{ number_format((float) $receipt->grand_total, 2) }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-light btn-xs js-grn-details"
                                    data-grn-no="{{ $receipt->grn_no }}"
                                    data-items='@json($receipt->items->map(fn($item) => ["product" => $item->product?->name, "batch_no" => $item->batch_no, "expiry_date" => optional($item->expiry_date)->toDateString(), "received_qty" => (float) $item->received_qty, "unit_cost" => (float) $item->unit_cost, "line_total" => (float) $item->line_total])->values())'
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
            {{ $receipts->links() }}
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const wrapper = document.getElementById('grn-items');
                const addRowBtn = document.getElementById('add-grn-row');
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
                        <input name="items[${idx}][batch_no]" placeholder="{{ __('app.batch_no') }}" required>
                        <input type="date" name="items[${idx}][expiry_date]">
                        <input type="number" step="0.001" name="items[${idx}][received_qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[${idx}][unit_cost]" placeholder="{{ __('app.unit_cost') }}">
                    `;
                    row.prepend(select);
                    wrapper.appendChild(row);
                    idx++;
                });

                document.querySelectorAll('.js-grn-details').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const grnNo = btn.getAttribute('data-grn-no') || '';
                        const items = JSON.parse(btn.getAttribute('data-items') || '[]');

                        let html = `<div class="swal-details"><table class="swal-table"><thead><tr><th>{{ __('app.product') }}</th><th>{{ __('app.batch_no') }}</th><th>{{ __('app.expiry_date') }}</th><th>{{ __('app.received_qty') }}</th><th>{{ __('app.unit_cost') }}</th><th>{{ __('app.total') }}</th></tr></thead><tbody>`;
                        if (!items.length) {
                            html += `<tr><td colspan="6">{{ __('app.no_data') }}</td></tr>`;
                        } else {
                            items.forEach((item) => {
                                html += `<tr><td>${item.product ?? '-'}</td><td>${item.batch_no ?? '-'}</td><td>${item.expiry_date ?? '-'}</td><td>${item.received_qty}</td><td>${item.unit_cost}</td><td>${item.line_total}</td></tr>`;
                            });
                        }
                        html += `</tbody></table></div>`;

                        Swal.fire({
                            title: `{{ __('app.grn_no') }}: ${grnNo}`,
                            html,
                            width: 980,
                            confirmButtonText: @json(__('app.close')),
                        });
                    });
                });
            })();
        </script>
    @endpush
@endsection
