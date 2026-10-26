{{-- Insignia de calificación. Uso: @include('partials.calificacion', ['cal' => $cals[$cliente->id] ?? null]) --}}
@if (! empty($cal))
    @php $estrellas = ['excelente' => 4, 'buena' => 3, 'regular' => 2, 'mala' => 1, 'nuevo' => 0][$cal['nivel']]; @endphp
    <span class="badge badge-soft-{{ $cal['color'] === 'primary' ? 'success' : $cal['color'] }} fw-semibold"
          title="{{ $cal['puntaje'] === null ? 'Sin cuotas vencidas todavía' : $cal['puntaje'].'/100 · '.$cal['atrasadas'].' de '.$cal['vencidas'].' cuotas vencidas sin pagar' }}">
        @if ($estrellas)
            @for ($i = 0; $i < $estrellas; $i++)<i class="bi bi-star-fill"></i>@endfor
        @else <i class="bi bi-person-plus"></i> @endif
        {{ $cal['etiqueta'] }}@if ($cal['puntaje'] !== null) · {{ $cal['puntaje'] }}@endif
    </span>
@endif
