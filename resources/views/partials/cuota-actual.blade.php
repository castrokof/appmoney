{{-- Insignia pequeña: en qué cuota va el préstamo (la próxima por pagar) --}}
@php $act = $p->cuota_actual; @endphp
@if ($act !== null)
    <span class="badge badge-soft-primary" title="La próxima cuota por pagar es la {{ $act }} de {{ $p->num_cuotas }}">
        <i class="bi bi-hash"></i>Cuota {{ $act }}/{{ $p->num_cuotas }}
    </span>
@elseif ($p->estado === 'pagado')
    <span class="badge badge-soft-success"><i class="bi bi-check2-all"></i> {{ $p->num_cuotas }}/{{ $p->num_cuotas }}</span>
@endif
