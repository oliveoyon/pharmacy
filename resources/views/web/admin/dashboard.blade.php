@extends('web.layouts.app')

@php($title = __('app.dashboard'))

@section('content')
    <div class="stats-grid">
        <article class="stat-card">
            <p>{{ __('app.total_branches') }}</p>
            <h3>{{ $stats['branches'] }}</h3>
        </article>
        <article class="stat-card">
            <p>{{ __('app.total_suppliers') }}</p>
            <h3>{{ $stats['suppliers'] }}</h3>
        </article>
        <article class="stat-card">
            <p>{{ __('app.total_products') }}</p>
            <h3>{{ $stats['products'] }}</h3>
        </article>
        <article class="stat-card success">
            <p>{{ __('app.sales_today') }}</p>
            <h3>{{ number_format($stats['sales_today'], 2) }}</h3>
        </article>
    </div>

    <div class="content-grid">
        <section class="panel">
            <h4>{{ __('app.quick_actions') }}</h4>
            <div class="chip-grid">
                @can('products.manage')
                    <button class="chip">{{ __('app.add_product') }}</button>
                @endcan
                @can('purchase.manage')
                    <button class="chip">{{ __('app.new_grn') }}</button>
                @endcan
                @can('sales.pos')
                    <button class="chip">{{ __('app.open_pos') }}</button>
                @endcan
                @can('reports.view')
                    <button class="chip">{{ __('app.view_reports') }}</button>
                @endcan
            </div>
        </section>

        <section class="panel">
            <h4>{{ __('app.system_readiness') }}</h4>
            <ul class="readiness-list">
                <li>{{ __('app.ready_auth') }}</li>
                <li>{{ __('app.ready_rbac') }}</li>
                <li>{{ __('app.ready_inventory') }}</li>
                <li>{{ __('app.ready_reports') }}</li>
            </ul>
        </section>
    </div>
@endsection

