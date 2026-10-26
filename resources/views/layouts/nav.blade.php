@php
    $items = [
        ['hoy', 'bi-cash-coin', 'Hoy'],
        ['atrasados', 'bi-exclamation-triangle', 'Atrasados'],
        ['clientes.index', 'bi-people', 'Clientes'],
        ['prestamos.index', 'bi-journal-text', 'Préstamos'],
    ];
    $mas = [
        ['gastos.index', 'bi-receipt', 'Gastos', false],
        ['bases.index', 'bi-bank', 'Base de dinero', true],
        ['resumen', 'bi-bar-chart-line', 'Resumen', true],
        ['usuarios.index', 'bi-person-gear', 'Usuarios', true],
    ];
    $gestor = auth()->user()->esGestor();
    $grupo = fn ($n) => explode('.', $n)[0];
@endphp
<nav class="bottom-nav">
    @foreach ($items as [$name, $icon, $label])
        <a href="{{ route($name) }}" class="{{ request()->routeIs($grupo($name).'*') ? 'active' : '' }}">
            <i class="bi {{ $icon }}"></i>{{ $label }}
        </a>
    @endforeach
    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMas"><i class="bi bi-grid"></i>Más</button>
</nav>

<div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="menuMas" style="height:auto">
    <div class="offcanvas-header pb-0">
        <h5 class="offcanvas-title fw-bold">Más opciones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="list-group list-group-flush">
            @foreach ($mas as [$name, $icon, $label, $soloGestor])
                @if (! $soloGestor || $gestor)
                    <a class="list-group-item list-group-item-action py-3" href="{{ route($name) }}">
                        <i class="bi {{ $icon }} me-2 text-primary"></i>{{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
