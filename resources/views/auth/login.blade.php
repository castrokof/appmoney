<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="p-0">
    <div class="login-wrap">
        <div class="login-card">
            <div class="text-center text-white mb-4">
                <i class="bi bi-wallet2 display-3"></i>
                <h1 class="h3 fw-bold mt-2">{{ config('app.name') }}</h1>
                <p class="opacity-75 mb-0">Gestión de préstamos y cobros</p>
            </div>
            <div class="card card-soft p-4">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Correo</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="form-control form-control-lg @error('email') is-invalid @enderror"
                               required autofocus autocomplete="username">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="password">Contraseña</label>
                        <input id="password" type="password" name="password"
                               class="form-control form-control-lg" required autocomplete="current-password">
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Mantener sesión iniciada</label>
                    </div>
                    <button class="btn btn-primary btn-lg w-100 fw-bold">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
