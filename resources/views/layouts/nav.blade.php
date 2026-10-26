@php
    $items = [
        ['hoy', 'bi-cash-coin', 'Hoy', '/'],
    ];
@endphp
<nav class="bottom-nav">
    @foreach ($items as [$name, $icon, $label])
        <a href="{{ route($name) }}" class="{{ request()->routeIs($name) ? 'active' : '' }}">
            <i class="bi {{ $icon }}"></i>{{ $label }}
        </a>
    @endforeach
</nav>
