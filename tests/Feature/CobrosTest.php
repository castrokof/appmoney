<?php

namespace Tests\Feature;

use App\Models\Base;
use App\Models\Cliente;
use App\Models\Gasto;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\PagoService;
use App\Services\PrestamoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CobrosTest extends TestCase
{
    use RefreshDatabase;

    private function cobrador(float $base = 1000000): User
    {
        $u = User::factory()->create(['role' => 'cobrador', 'empresa_id' => 1]);
        if ($base) {
            Base::create(['user_id' => $u->id, 'monto' => $base, 'fecha' => today(), 'creado_por' => $u->id]);
        }

        return $u;
    }

    private function prestamo(User $u, array $over = []): Prestamo
    {
        $cliente = Cliente::create(['user_id' => $u->id, 'nombres' => 'Ana']);

        return app(PrestamoService::class)->crear($over + [
            'user_id' => $u->id, 'cliente_id' => $cliente->id, 'monto' => 100000,
            'interes_pct' => 20, 'num_cuotas' => 10, 'frecuencia' => 'diario',
            'saltar_domingos' => true, 'fecha_inicio' => '2026-01-04', // domingo
        ]);
    }

    public function test_cuotas_diarias_saltan_domingos_y_suman_el_total(): void
    {
        $p = $this->prestamo($this->cobrador());

        $this->assertCount(10, $p->cuotas);
        $this->assertSame(120000.0, (float) $p->cuotas->sum('valor'));
        foreach ($p->cuotas as $c) {
            $this->assertFalse($c->fecha->isSunday());
        }
        $this->assertSame('2026-01-05', $p->cuotas->first()->fecha->toDateString());
    }

    public function test_cuotas_mensuales_suman_un_mes(): void
    {
        $p = $this->prestamo($this->cobrador(), ['frecuencia' => 'mensual', 'num_cuotas' => 3, 'fecha_inicio' => '2026-01-31']);

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-28'], $p->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());
    }

    public function test_ultima_cuota_ajusta_el_redondeo(): void
    {
        $p = $this->prestamo($this->cobrador(), ['monto' => 100000, 'interes_pct' => 10, 'num_cuotas' => 3, 'frecuencia' => 'semanal']);

        $this->assertSame(110000.0, (float) $p->cuotas->sum('valor'));
    }

    public function test_pago_exacto_marca_pagada_y_abono_deja_parcial(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        $s = app(PagoService::class);

        $s->aplicar($p, 12000, today()->toDateString(), $u->id);
        $this->assertSame('pagada', $p->cuotas()->first()->estado);

        $s->aplicar($p, 5000, today()->toDateString(), $u->id);
        $c2 = $p->cuotas()->where('numero', 2)->first();
        $this->assertSame('pendiente', $c2->estado);
        $this->assertSame(5000.0, (float) $c2->pagado);
    }

    public function test_pago_mayor_a_una_cuota_cubre_la_siguiente(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);

        app(PagoService::class)->aplicar($p, 30000, today()->toDateString(), $u->id);

        $cuotas = $p->cuotas()->get();
        $this->assertSame('pagada', $cuotas[0]->estado);
        $this->assertSame('pagada', $cuotas[1]->estado);
        $this->assertSame(6000.0, (float) $cuotas[2]->pagado);
    }

    public function test_pago_mayor_al_saldo_es_rechazado(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);

        $this->expectException(ValidationException::class);
        app(PagoService::class)->aplicar($p, 120001, today()->toDateString(), $u->id);
    }

    public function test_pagar_todo_marca_prestamo_pagado_y_deshacer_lo_reactiva(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        $s = app(PagoService::class);

        $s->aplicar($p, 50000, today()->toDateString(), $u->id);
        $ultimo = $s->aplicar($p, 70000, today()->toDateString(), $u->id);
        $this->assertSame('pagado', $p->fresh()->estado);

        $s->deshacer($ultimo);

        $this->assertSame('activo', $p->fresh()->estado);
        $this->assertSame(70000.0, round((float) $p->fresh()->saldo, 2));
        $this->assertSame(50000.0, (float) $p->cuotas()->sum('pagado'));
    }

    public function test_caja_se_calcula_y_bloquea_prestamos_sin_base(): void
    {
        $u = $this->cobrador(150000);
        Gasto::create(['user_id' => $u->id, 'monto' => 10000, 'descripcion' => 'Gasolina', 'fecha' => today()]);
        $p = $this->prestamo($u); // presta 100.000
        app(PagoService::class)->aplicar($p, 20000, today()->toDateString(), $u->id);

        $caja = app(\App\Services\CajaService::class);
        $this->assertSame(60000.0, $caja->caja($u->id)); // 150k - 100k + 20k - 10k

        $this->expectException(ValidationException::class);
        $this->prestamo($u, ['monto' => 80000]);
    }

    public function test_anular_devuelve_el_monto_a_la_caja(): void
    {
        $u = $this->cobrador(100000);
        $p = $this->prestamo($u);
        $caja = app(\App\Services\CajaService::class);
        $this->assertSame(0.0, $caja->caja($u->id));

        app(PrestamoService::class)->anular($p);

        $this->assertSame(100000.0, $caja->caja($u->id));
    }

    public function test_cobrador_no_ve_ni_paga_prestamos_ajenos(): void
    {
        $a = $this->cobrador();
        $b = $this->cobrador();
        $p = $this->prestamo($a);

        $this->actingAs($b)->get(route('prestamos.show', $p->id))->assertNotFound();
        $this->actingAs($b)->postJson(route('pagos.store', $p->id), ['valor' => 1000])->assertNotFound();
        $this->actingAs($a)->get(route('prestamos.show', $p->id))->assertOk();
    }

    public function test_cobrador_no_ve_gastos_de_otro_ni_edita(): void
    {
        $a = $this->cobrador();
        $b = $this->cobrador();
        $g = Gasto::create(['user_id' => $a->id, 'monto' => 5000, 'descripcion' => 'Almuerzo', 'fecha' => today()]);

        $this->actingAs($b)->get(route('gastos.index'))->assertDontSee('Almuerzo');
        $this->actingAs($b)->put(route('gastos.update', $g), ['monto' => 1, 'descripcion' => 'x'])->assertNotFound();
    }

    public function test_solo_gestores_acceden_a_bases_resumen_y_usuarios(): void
    {
        $c = $this->cobrador();
        $this->actingAs($c)->get(route('bases.index'))->assertForbidden();
        $this->actingAs($c)->get(route('resumen'))->assertForbidden();
        $this->actingAs($c)->get(route('usuarios.index'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('bases.index'))->assertOk();
        $this->actingAs($admin)->get(route('resumen'))->assertOk();
        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk();
    }

    public function test_pantallas_principales_cargan_con_datos(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(3)->toDateString(), 'saltar_domingos' => false]);

        foreach (['hoy', 'atrasados', 'clientes.index', 'prestamos.index', 'gastos.index', 'prestamos.create', 'clientes.create'] as $ruta) {
            $this->actingAs($u)->get(route($ruta))->assertOk();
        }
        $this->actingAs($u)->get(route('hoy'))->assertSee('Ana');
        $this->actingAs($u)->get(route('prestamos.show', $p->id))->assertOk();
    }

    public function test_empresa_ve_solo_su_empresa(): void
    {
        $mia = $this->cobrador();
        $ajena = User::factory()->create(['role' => 'cobrador', 'empresa_id' => 2]);
        Base::create(['user_id' => $ajena->id, 'monto' => 500000, 'fecha' => today(), 'creado_por' => $ajena->id]);
        $pAjeno = $this->prestamo($ajena);
        $empresa = User::factory()->create(['role' => 'empresa', 'empresa_id' => 1]);

        $this->actingAs($empresa)->get(route('prestamos.show', $pAjeno->id))->assertNotFound();
        $this->actingAs($empresa)->post(route('bases.store'), ['user_id' => $ajena->id, 'monto' => 1000])->assertForbidden();
    }

    public function test_calificacion_nuevo_buena_y_mala(): void
    {
        $u = $this->cobrador();
        $cal = app(\App\Services\CalificacionService::class);

        // Sin cuotas vencidas: Nuevo
        $nuevo = $this->prestamo($u, ['fecha_inicio' => today()->addDays(2)->toDateString(), 'saltar_domingos' => false]);
        $this->assertSame('nuevo', $cal->paraCliente($nuevo->cliente_id)['nivel']);

        // 10 cuotas vencidas, todas pagadas: Excelente (100)
        $bueno = $this->prestamo($u, ['fecha_inicio' => today()->subDays(10)->toDateString(), 'saltar_domingos' => false]);
        app(PagoService::class)->aplicar($bueno, 120000, today()->toDateString(), $u->id);
        $r = $cal->paraCliente($bueno->cliente_id);
        $this->assertSame('excelente', $r['nivel']);
        $this->assertSame(100, $r['puntaje']);

        // 10 cuotas vencidas, ninguna pagada, la más vieja hace 10 días: 100 - 60 - 20 = 20 → Mala
        $malo = $this->prestamo($u, ['fecha_inicio' => today()->subDays(10)->toDateString(), 'saltar_domingos' => false]);
        $r = $cal->paraCliente($malo->cliente_id);
        $this->assertSame('mala', $r['nivel']);
        $this->assertSame(20, $r['puntaje']);
        $this->assertSame(10, $r['dias_max']);
    }

    public function test_calificacion_se_muestra_en_las_pantallas(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(10)->toDateString(), 'saltar_domingos' => false]);

        foreach ([route('hoy'), route('atrasados'), route('clientes.index'), route('prestamos.index'), route('prestamos.show', $p->id), route('prestamos.create')] as $url) {
            $this->actingAs($u)->get($url)->assertOk();
        }
        $this->actingAs($u)->get(route('hoy'))->assertSee('Mala');
        $this->actingAs($u)->get(route('prestamos.show', $p->id))->assertSee('Calificación del cliente');
    }

    public function test_la_app_es_instalable_pwa(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('serviceWorker', false);

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }
}
