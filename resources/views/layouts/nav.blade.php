@php
    $items = [
        ['hoy', 'bi-cash-coin', 'Hoy'],
        ['atrasados', 'bi-exclamation-triangle', 'Atrasados'],
        ['clientes.index', 'bi-people', 'Clientes'],
        ['prestamos.index', 'bi-journal-text', 'Préstamos'],
    ];
    $mas = [
        ['ruta.index', 'bi-signpost-split', 'Ordenar ruta', false, false],
        ['gastos.index', 'bi-receipt', 'Gastos', false, false],
        ['bases.index', 'bi-bank', 'Base de dinero', true, true],
        ['resumen', 'bi-bar-chart-line', 'Resumen', true, false],
        ['usuarios.index', 'bi-person-gear', 'Usuarios', true, false],
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
        <button type="button" data-instalar class="btn btn-primary w-100 fw-bold rounded-pill mb-2 d-none"><i class="bi bi-download"></i> Instalar app en el celular</button>
        <div class="alert alert-info small py-2 d-none" data-instalar-ios>Para instalar: toca <i class="bi bi-box-arrow-up"></i> Compartir y luego «Añadir a pantalla de inicio».</div>
        <div class="list-group list-group-flush">
            @foreach ($mas as [$name, $icon, $label, $soloGestor, $requiereBase])
                @if ((! $soloGestor || $gestor) && (empty($requiereBase) || config('cobros.usar_base')))
                    <a class="list-group-item list-group-item-action py-3" href="{{ route($name) }}">
                        <i class="bi {{ $icon }} me-2 text-primary"></i>{{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
