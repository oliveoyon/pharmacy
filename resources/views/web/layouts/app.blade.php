<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="admin-shell {{ app()->getLocale() === 'bn' ? 'lang-bn' : '' }}">
    <div class="bg-pattern"></div>
    <aside class="sidebar">
        <div class="brand-block">
            <div class="brand-badge">Rx</div>
            <div>
                <h1>{{ __('app.app_name') }}</h1>
                <p>{{ __('app.tagline') }}</p>
            </div>
        </div>

        <nav class="nav-group">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span>{{ __('app.dashboard') }}</span>
            </a>

            @can('products.manage')
                <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">{{ __('app.products') }}</a>
            @endcan
            @can('units.manage')
                <a href="{{ route('admin.units.index') }}" class="nav-link {{ request()->routeIs('admin.units.*') ? 'active' : '' }}">{{ __('app.units') }}</a>
            @endcan
            @can('suppliers.manage')
                <a href="{{ route('admin.suppliers.index') }}" class="nav-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">{{ __('app.suppliers') }}</a>
            @endcan
            @can('purchase.manage')
                <a href="{{ route('admin.purchase-orders.index') }}" class="nav-link {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}">{{ __('app.purchase_orders') }}</a>
                <a href="{{ route('admin.goods-receipts.index') }}" class="nav-link {{ request()->routeIs('admin.goods-receipts.*') ? 'active' : '' }}">{{ __('app.goods_receipts') }}</a>
            @endcan
            @can('sales.pos')
                <a href="{{ route('admin.pos.index') }}" class="nav-link {{ request()->routeIs('admin.pos.*') ? 'active' : '' }}">{{ __('app.pos_console') }}</a>
            @endcan
            @can('reports.view')
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">{{ __('app.reports') }}</a>
            @endcan
        </nav>

        <div class="sidebar-footer">
            <small>{{ __('app.version') }} 1.0.0</small>
        </div>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div class="page-title">
                <h2>{{ $title ?? __('app.dashboard') }}</h2>
                <p>{{ __('app.welcome_line') }}</p>
            </div>

            <div class="topbar-actions">
                <form method="POST" action="{{ route('web.locale', app()->getLocale() === 'en' ? 'bn' : 'en') }}">
                    @csrf
                    <button type="submit" class="btn btn-light">
                        {{ app()->getLocale() === 'en' ? 'বাংলা' : 'English' }}
                    </button>
                </form>

                @auth
                    <div class="user-chip">
                        <span>{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('web.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-danger">{{ __('app.logout') }}</button>
                    </form>
                @endauth
            </div>
        </header>

        <section class="content-wrapper">
            @yield('content')
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (() => {
            const toast = @json(session('toast'));
            if (toast) {
                Swal.fire({
                    icon: toast.type || 'success',
                    title: toast.title || 'Done',
                    text: toast.text || '',
                    timer: 2200,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                });
            }

            const firstError = @json($errors->first());
            if (firstError) {
                Swal.fire({
                    icon: 'error',
                    title: @json(__('app.validation_error')),
                    text: firstError
                });
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
