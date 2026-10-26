@extends('layouts.app')
@section('title', 'Auditoría')
@section('subtitle', 'Quién cambió qué, cuándo y por qué')
@section('content')
    <form method="GET" class="card card-soft mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-6"><label class="form-label small mb-0">Desde</label><input type="date" name="desde" value="{{ $desde }}" class="form-control"></div>
        <div class="col-6"><label class="form-label small mb-0">Hasta</label><input type="date" name="hasta" value="{{ $hasta }}" class="form-control"></div>
        <div class="col-6"><label class="form-label small mb-0">Usuario</label>
            <select name="user_id" class="form-select"><option value="">Todos</option>
                @foreach ($usuarios as $u)<option value="{{ $u->id }}" @selected($usuarioId == $u->id)>{{ $u->name }}</option>@endforeach
            </select></div>
        <div class="col-6"><label class="form-label small mb-0">Acción</label>
            <select name="accion" class="form-select"><option value="">Todas</option>
                @foreach (\App\Models\Auditoria::ACCIONES as $k => [$etq])<option value="{{ $k }}" @selected($accion === $k)>{{ $etq }}</option>@endforeach
            </select></div>
        <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button></div>
    </div></form>

    @forelse ($registros as $a)
        @php [$etq, $color, $icono] = $a->etiqueta; @endphp
        <div class="card card-soft mb-2"><div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <span class="badge badge-soft-{{ $color === 'primary' ? 'success' : $color }}"><i class="bi {{ $icono }}"></i> {{ $etq }}</span>
                <span class="small text-secondary text-end">{{ $a->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="mt-1">{{ $a->resumen }}</div>
            @if ($a->motivo)<div class="small fst-italic text-secondary">Motivo: «{{ $a->motivo }}»</div>@endif
            <div class="small text-secondary d-flex justify-content-between">
                <span>Por {{ $a->user->name ?? 'sistema' }}@if($a->ip) · {{ $a->ip }}@endif</span>
                @if ($a->prestamo_id)<a href="{{ route('prestamos.show', $a->prestamo_id) }}">Préstamo #{{ $a->prestamo_id }}</a>@endif
            </div>
        </div></div>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">Sin registros en este período.</div></div>
    @endforelse

    {{ $registros->links() }}
@endsection
