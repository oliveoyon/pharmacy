@extends('web.layouts.app')

@php($title = __('app.pos_console'))

@section('content')
    <div class="pos-layout">
        <section class="panel">
            <h4>{{ __('app.counter_sessions') }}</h4>
            <form method="POST" action="{{ route('admin.pos.counter-sessions.open') }}" class="form-grid compact">
                @csrf
                <label>{{ __('app.branch') }}</label>
                <select name="branch_id" required>
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
                <label>{{ __('app.counter_code') }}</label>
                <input name="counter_code">
                <label>{{ __('app.opening_cash') }}</label>
                <input type="number" step="0.01" name="opening_cash">
                <button class="btn btn-primary" type="submit">{{ __('app.open_session') }}</button>
            </form>

            <div class="table-wrap mt-sm">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('app.branch') }}</th>
                        <th>{{ __('app.counter_code') }}</th>
                        <th>{{ __('app.opened_at') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($openSessions as $session)
                        <tr>
                            <td>#{{ $session->id }}</td>
                            <td>{{ $session->branch_id }}</td>
                            <td>{{ $session->counter_code }}</td>
                            <td>{{ optional($session->opened_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.pos.counter-sessions.close', $session) }}" class="inline-form">
                                    @csrf
                                    <input type="number" step="0.01" name="closing_cash" placeholder="{{ __('app.closing_cash') }}">
                                    <button class="btn btn-danger btn-xs" type="submit">{{ __('app.close_session') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h4>{{ __('app.new_sale') }}</h4>
            <form method="POST" action="{{ route('admin.pos.sales') }}" class="form-grid">
                @csrf
                <label>{{ __('app.branch') }}</label>
                <select name="branch_id" required>
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>

                <label>{{ __('app.counter_session_optional') }}</label>
                <select name="counter_session_id">
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($openSessions as $session)
                        <option value="{{ $session->id }}">#{{ $session->id }} {{ $session->counter_code }}</option>
                    @endforeach
                </select>

                <label>{{ __('app.invoice_type') }}</label>
                <select name="invoice_type">
                    <option value="retail_cash">retail_cash</option>
                    <option value="retail_credit">retail_credit</option>
                    <option value="wholesale">wholesale</option>
                </select>

                <div id="sale-items" class="line-items">
                    <h5>{{ __('app.items') }}</h5>
                    <div class="line-row">
                        <select name="items[0][product_id]" required>
                            <option value="">{{ __('app.product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.001" name="items[0][qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[0][unit_price]" placeholder="{{ __('app.unit_price') }}">
                        <input type="number" step="0.01" name="items[0][tax_percent]" placeholder="{{ __('app.tax_percent') }}">
                        <input type="number" step="0.01" name="items[0][discount_amount]" placeholder="{{ __('app.discount') }}">
                    </div>
                </div>
                <button type="button" id="add-sale-row" class="btn btn-light">{{ __('app.add_line') }}</button>

                <div class="line-items">
                    <h5>{{ __('app.payments') }}</h5>
                    <div class="line-row">
                        <input name="payments[0][payment_method]" placeholder="{{ __('app.payment_method') }}" value="cash" required>
                        <input type="number" step="0.01" name="payments[0][amount]" placeholder="{{ __('app.amount') }}" required>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('app.post_sale') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.sales_return') }}</h4>
            <form method="POST" action="{{ route('admin.pos.returns') }}" class="form-grid">
                @csrf
                <label>{{ __('app.invoice') }}</label>
                <select name="sales_invoice_id" required>
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($recentInvoices as $invoice)
                        <option value="{{ $invoice->id }}">#{{ $invoice->id }} - {{ $invoice->invoice_no }}</option>
                    @endforeach
                </select>
                <label>{{ __('app.reason') }}</label>
                <input name="reason">

                <div class="line-items" id="return-items">
                    <h5>{{ __('app.items') }}</h5>
                    <div class="line-row">
                        <select name="items[0][sales_invoice_item_id]" class="return-item-select" required>
                            <option value="">{{ __('app.select_invoice_first') }}</option>
                        </select>
                        <input type="number" step="0.001" name="items[0][return_qty]" placeholder="{{ __('app.return_qty') }}" required>
                    </div>
                </div>
                <button type="button" id="add-return-row" class="btn btn-light">{{ __('app.add_line') }}</button>
                <button class="btn btn-primary" type="submit">{{ __('app.post_return') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.recent_invoices') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('app.invoice_no') }}</th>
                        <th>{{ __('app.total') }}</th>
                        <th>{{ __('app.paid') }}</th>
                        <th>{{ __('app.items') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentInvoices as $invoice)
                        <tr>
                            <td>#{{ $invoice->id }}</td>
                            <td>{{ $invoice->invoice_no }}</td>
                            <td>{{ number_format((float) $invoice->grand_total, 2) }}</td>
                            <td>{{ number_format((float) $invoice->paid_total, 2) }}</td>
                            <td>
                                @foreach($invoice->items as $item)
                                    <div class="mini-line">#{{ $item->id }} {{ $item->product?->name }} x {{ (float) $item->sold_qty }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const products = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->values());
                const invoices = @json(
                    $recentInvoices->map(fn($invoice) => [
                        'id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'items' => $invoice->items->map(fn($item) => [
                            'id' => $item->id,
                            'product_name' => $item->product?->name,
                            'sold_qty' => (float) $item->sold_qty,
                            'returned_qty' => (float) ($item->return_items_sum_return_qty ?? 0),
                        ])->values(),
                    ])->values()
                );
                let saleIdx = 1;
                let returnIdx = 1;

                const saleWrap = document.getElementById('sale-items');
                const returnWrap = document.getElementById('return-items');
                const invoiceSelect = document.querySelector('select[name="sales_invoice_id"]');

                const invoiceMap = new Map(invoices.map(inv => [String(inv.id), inv]));

                const buildItemOptions = () => {
                    const selectedInvoiceId = invoiceSelect?.value ? String(invoiceSelect.value) : '';
                    const selectedInvoice = selectedInvoiceId ? invoiceMap.get(selectedInvoiceId) : null;
                    if (!selectedInvoice) {
                        return `<option value="">{{ __('app.select_invoice_first') }}</option>`;
                    }

                    const options = selectedInvoice.items
                        .map(item => ({
                            ...item,
                            returnable_qty: Math.max(0, Number(item.sold_qty) - Number(item.returned_qty || 0)),
                        }))
                        .filter(item => item.returnable_qty > 0)
                        .map(item => {
                        const label = `#${item.id} ${item.product_name ?? 'Item'} ({{ __('app.returnable_qty') }}: ${item.returnable_qty})`;
                        return `<option value="${item.id}">${label}</option>`;
                    }).join('');

                    return `<option value="">{{ __('app.select_one') }}</option>${options}`;
                };

                const refreshReturnItemSelects = () => {
                    const html = buildItemOptions();
                    document.querySelectorAll('.return-item-select').forEach((select) => {
                        const previous = select.value;
                        select.innerHTML = html;
                        if (previous && select.querySelector(`option[value="${previous}"]`)) {
                            select.value = previous;
                        }
                    });
                };

                document.getElementById('add-sale-row')?.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'line-row';
                    const select = document.createElement('select');
                    select.name = `items[${saleIdx}][product_id]`;
                    select.required = true;
                    select.innerHTML = `<option value="">{{ __('app.product') }}</option>` + products.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
                    row.innerHTML = `
                        <input type="number" step="0.001" name="items[${saleIdx}][qty]" placeholder="{{ __('app.qty') }}" required>
                        <input type="number" step="0.0001" name="items[${saleIdx}][unit_price]" placeholder="{{ __('app.unit_price') }}">
                        <input type="number" step="0.01" name="items[${saleIdx}][tax_percent]" placeholder="{{ __('app.tax_percent') }}">
                        <input type="number" step="0.01" name="items[${saleIdx}][discount_amount]" placeholder="{{ __('app.discount') }}">
                    `;
                    row.prepend(select);
                    saleWrap.appendChild(row);
                    saleIdx++;
                });

                document.getElementById('add-return-row')?.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'line-row';
                    row.innerHTML = `
                        <select name="items[${returnIdx}][sales_invoice_item_id]" class="return-item-select" required>${buildItemOptions()}</select>
                        <input type="number" step="0.001" name="items[${returnIdx}][return_qty]" placeholder="{{ __('app.return_qty') }}" required>
                    `;
                    returnWrap.appendChild(row);
                    returnIdx++;
                });

                invoiceSelect?.addEventListener('change', refreshReturnItemSelects);
                refreshReturnItemSelects();
            })();
        </script>
    @endpush
@endsection
