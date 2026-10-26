@extends('layouts.app')
@section('title', 'Cobro de hoy')
@section('content')
    <div class="card card-soft"><div class="card-body text-center py-5">
        <i class="bi bi-rocket-takeoff display-4 text-primary"></i>
        <h2 class="h5 mt-3">Base lista</h2>
        <p class="text-secondary mb-0">Sesión iniciada como <strong>{{ auth()->user()->role }}</strong>.</p>
    </div></div>
@endsection
