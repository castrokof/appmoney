<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <title>@yield('title', 'Cobros') · {{ config('app.name') }}</title>
    @include('partials.pwa-head')
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <header class="app-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <h1>@yield('title', 'Cobros')</h1>
                <div class="sub">@yield('subtitle', now()->translatedFormat('l j \d\e F'))</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-user" title="Salir">
                    <i class="bi bi-box-arrow-right"></i> {{ auth()->user()->name }}
                </button>
            </form>
        </div>
    </header>

    <main class="container app-main">
        @if (session('ok'))
            <div class="alert alert-success card-soft">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger card-soft mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @include('layouts.nav')

    @include('partials.motivo')
    @include('partials.pwa-script')
    @stack('scripts')
</body>
</html>
