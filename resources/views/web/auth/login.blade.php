<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('app.login') }} | {{ __('app.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-shell {{ app()->getLocale() === 'bn' ? 'lang-bn' : '' }}">
    <div class="login-bg"></div>

    <div class="login-grid">
        <section class="login-brand-panel">
            <span class="label">{{ __('app.enterprise_grade') }}</span>
            <h1>{{ __('app.app_name') }}</h1>
            <p>{{ __('app.login_intro') }}</p>
            <ul>
                <li>{{ __('app.feature_multi_branch') }}</li>
                <li>{{ __('app.feature_fefo') }}</li>
                <li>{{ __('app.feature_reports') }}</li>
            </ul>
        </section>

        <section class="login-card">
            <div class="login-top">
                <h2>{{ __('app.login') }}</h2>
                <form method="POST" action="{{ route('web.locale', app()->getLocale() === 'en' ? 'bn' : 'en') }}">
                    @csrf
                    <button type="submit" class="btn btn-light">
                        {{ app()->getLocale() === 'en' ? 'বাংলা' : 'English' }}
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('web.login.store') }}">
                @csrf
                <label>{{ __('app.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">

                <label>{{ __('app.password') }}</label>
                <input type="password" name="password" required autocomplete="current-password">

                <label class="checkbox-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>{{ __('app.remember_me') }}</span>
                </label>

                <button class="btn btn-primary" type="submit">{{ __('app.login') }}</button>
            </form>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const firstError = @json($errors->first());
        if (firstError) {
            Swal.fire({
                icon: 'error',
                title: @json(__('app.login_failed')),
                text: firstError
            });
        }
    </script>
</body>
</html>

