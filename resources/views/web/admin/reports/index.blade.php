@extends('web.layouts.app')

@php($title = __('app.reports'))

@section('content')
    <div class="crud-grid wide">
        <section class="panel">
            <h4>{{ __('app.reports') }}</h4>
            <form method="GET" action="{{ route('admin.reports.index') }}" class="form-grid">
                <label>{{ __('app.report_type') }}</label>
                <select name="section">
                    <option value="sales_summary" @selected($section === 'sales_summary')>{{ __('app.sales_summary') }}</option>
                    <option value="stock_valuation" @selected($section === 'stock_valuation')>{{ __('app.stock_valuation') }}</option>
                    <option value="expiry_alerts" @selected($section === 'expiry_alerts')>{{ __('app.expiry_alerts') }}</option>
                </select>

                <label>{{ __('app.branch') }}</label>
                <select name="branch_id">
                    <option value="">{{ __('app.all_branches') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>
                            {{ $branch->name }} ({{ $branch->code }})
                        </option>
                    @endforeach
                </select>

                <label>{{ __('app.date_from') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">

                <label>{{ __('app.date_to') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">

                <label>{{ __('app.within_days') }}</label>
                <input type="number" min="1" max="3650" name="within_days" value="{{ $filters['within_days'] ?? 90 }}">

                <div class="row-actions">
                    <button class="btn btn-primary" type="submit">{{ __('app.apply_filters') }}</button>
                    <a class="btn btn-light" href="{{ route('admin.reports.export', ['reportType' => $section, 'branch_id' => $filters['branch_id'] ?? null, 'date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null, 'within_days' => $filters['within_days'] ?? null]) }}">
                        {{ __('app.export_csv') }}
                    </a>
                </div>
            </form>
        </section>

        @if($section === 'sales_summary' && $salesSummary)
            <section class="panel">
                <h4>{{ __('app.sales_summary') }}</h4>
                <div class="stats-grid">
                    <article class="stat-card"><span>{{ __('app.total') }}</span><strong>{{ number_format((float) ($salesSummary['totals']['grand_total'] ?? 0), 2) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.paid') }}</span><strong>{{ number_format((float) ($salesSummary['totals']['paid_total'] ?? 0), 2) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.due_total') }}</span><strong>{{ number_format((float) ($salesSummary['totals']['due_total'] ?? 0), 2) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.invoice_count') }}</span><strong>{{ (int) ($salesSummary['totals']['invoice_count'] ?? 0) }}</strong></article>
                </div>

                <div class="table-wrap mt-sm">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{{ __('app.invoice_type') }}</th>
                            <th>{{ __('app.invoice_count') }}</th>
                            <th>{{ __('app.total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($salesSummary['by_type'] as $row)
                            <tr>
                                <td>{{ $row['invoice_type'] }}</td>
                                <td>{{ (int) $row['invoice_count'] }}</td>
                                <td>{{ number_format((float) $row['grand_total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('app.no_data') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($section === 'stock_valuation' && $stockValuation)
            <section class="panel">
                <h4>{{ __('app.stock_valuation') }}</h4>
                <div class="stats-grid">
                    <article class="stat-card"><span>{{ __('app.line_count') }}</span><strong>{{ (int) ($stockValuation['summary']['line_count'] ?? 0) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.total_qty') }}</span><strong>{{ number_format((float) ($stockValuation['summary']['total_qty'] ?? 0), 3) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.total_cost_value') }}</span><strong>{{ number_format((float) ($stockValuation['summary']['total_cost_value'] ?? 0), 2) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.total_selling_value') }}</span><strong>{{ number_format((float) ($stockValuation['summary']['total_selling_value'] ?? 0), 2) }}</strong></article>
                </div>

                <div class="table-wrap mt-sm">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{{ __('app.branch') }}</th>
                            <th>{{ __('app.product') }}</th>
                            <th>{{ __('app.batch_no') }}</th>
                            <th>{{ __('app.expiry_date') }}</th>
                            <th>{{ __('app.qty') }}</th>
                            <th>{{ __('app.cost_value') }}</th>
                            <th>{{ __('app.selling_value') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($stockValuation['items'] as $row)
                            <tr>
                                <td>{{ $row['branch_name'] }}</td>
                                <td>{{ $row['product_name'] }} ({{ $row['sku'] }})</td>
                                <td>{{ $row['batch_no'] }}</td>
                                <td>{{ $row['expiry_date'] ?? '-' }}</td>
                                <td>{{ number_format((float) $row['qty'], 3) }}</td>
                                <td>{{ number_format((float) $row['cost_value'], 2) }}</td>
                                <td>{{ number_format((float) $row['selling_value'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('app.no_data') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($section === 'expiry_alerts' && $expiryAlerts)
            <section class="panel">
                <h4>{{ __('app.expiry_alerts') }}</h4>
                <div class="stats-grid">
                    <article class="stat-card"><span>{{ __('app.line_count') }}</span><strong>{{ (int) ($expiryAlerts['summary']['line_count'] ?? 0) }}</strong></article>
                    <article class="stat-card"><span>{{ __('app.total_qty') }}</span><strong>{{ number_format((float) ($expiryAlerts['summary']['total_qty'] ?? 0), 3) }}</strong></article>
                </div>

                <div class="table-wrap mt-sm">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{{ __('app.branch') }}</th>
                            <th>{{ __('app.product') }}</th>
                            <th>{{ __('app.batch_no') }}</th>
                            <th>{{ __('app.expiry_date') }}</th>
                            <th>{{ __('app.days_left') }}</th>
                            <th>{{ __('app.qty') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($expiryAlerts['items'] as $row)
                            <tr>
                                <td>{{ $row['branch_name'] }}</td>
                                <td>{{ $row['product_name'] }} ({{ $row['sku'] }})</td>
                                <td>{{ $row['batch_no'] }}</td>
                                <td>{{ $row['expiry_date'] }}</td>
                                <td>{{ $row['days_left'] }}</td>
                                <td>{{ number_format((float) $row['qty'], 3) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">{{ __('app.no_data') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
