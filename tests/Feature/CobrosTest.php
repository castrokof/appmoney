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

    protected function setUp(): void
    {
        parent::setUp();
        config(['cobros.usar_base' => true]); // la base está apagada por defecto; estas pruebas la ejercitan
    }

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
        $clienteId = $over['cliente_id'] ?? Cliente::create(['user_id' => $u->id, 'nombres' => 'Ana'])->id;

        return app(PrestamoService::class)->crear($over + [
            'user_id' => $u->id, 'cliente_id' => $clienteId,
            'valor_cuota' => 12000, 'num_cuotas' => 10, 'frecuencia' => 'diario',
            'saltar_domingos' => true, 'saltar_festivos' => false, 'fecha_inicio' => '2026-01-04', // domingo
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
        $p = $this->prestamo($this->cobrador(), ['frecuencia' => 'mensual', 'num_cuotas' => 3, 'fecha_inicio' => '2026-01-31', 'saltar_festivos' => false]);

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-28'], $p->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());
    }

    public function test_monto_y_total_son_cuota_por_numero_de_cuotas(): void
    {
        $p = $this->prestamo($this->cobrador(), ['valor_cuota' => 8500, 'num_cuotas' => 7, 'frecuencia' => 'semanal']);

        $this->assertSame(59500.0, (float) $p->monto);
        $this->assertSame(59500.0, (float) $p->monto_total);
        $this->assertSame(59500.0, (float) $p->cuotas->sum('valor'));
    }

    public function test_festivos_de_colombia_2026(): void
    {
        $esperados = ['2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-01', '2026-05-18',
            '2026-06-08', '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07', '2026-08-17', '2026-10-12',
            '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25'];

        $this->assertSame($esperados, array_keys(\App\Support\FestivosColombia::deAnio(2026)));
    }

    public function test_diario_salta_domingos_y_festivos_pero_los_demas_no(): void
    {
        $u = $this->cobrador();

        // 1 ene 2026 (festivo, jueves) → arranca el viernes 2; salta sáb? no: sábado se cobra; domingo 4 se salta
        $d = $this->prestamo($u, ['fecha_inicio' => '2026-01-01', 'num_cuotas' => 5, 'saltar_festivos' => true]);
        $this->assertSame(['2026-01-02', '2026-01-03', '2026-01-05', '2026-01-06', '2026-01-07'],
            $d->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());

        // Con festivos activos, el 12 ene (lunes festivo) se omite
        $e = $this->prestamo($u, ['fecha_inicio' => '2026-01-10', 'num_cuotas' => 3, 'saltar_festivos' => true]);
        $this->assertSame(['2026-01-10', '2026-01-13', '2026-01-14'], $e->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());

        // Sin saltar festivos, el 12 ene sí se cobra
        $f = $this->prestamo($u, ['fecha_inicio' => '2026-01-10', 'num_cuotas' => 3, 'saltar_festivos' => false]);
        $this->assertSame(['2026-01-10', '2026-01-12', '2026-01-13'], $f->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());

        // Semanal: no aplica ninguna exclusión (1 ene es festivo y se mantiene)
        $g = $this->prestamo($u, ['fecha_inicio' => '2026-01-01', 'num_cuotas' => 2, 'frecuencia' => 'semanal', 'saltar_festivos' => true, 'saltar_domingos' => true]);
        $this->assertSame(['2026-01-01', '2026-01-08'], $g->cuotas->map(fn ($c) => $c->fecha->toDateString())->all());
    }

    public function test_renovar_prestamo_pagado_copia_condiciones(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['saltar_festivos' => true]);
        app(PagoService::class)->aplicar($p, 120000, today()->toDateString(), $u->id);
        $this->assertSame('pagado', $p->fresh()->estado);

        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 120000, 'valor_cuota' => 15000, 'num_cuotas' => 8, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertRedirect();

        $nuevo = Prestamo::where('cliente_id', $p->cliente_id)->where('id', '!=', $p->id)->firstOrFail();
        $this->assertSame('activo', $nuevo->estado);
        $this->assertSame(120000.0, (float) $nuevo->monto_total);
        $this->assertSame('diario', $nuevo->frecuencia);
        $this->assertTrue($nuevo->saltar_festivos);
        $this->assertCount(8, $nuevo->cuotas);
        $this->assertSame($p->id, $nuevo->renovado_de_id);
    }

    public function test_renovar_con_cuotas_pendientes_descuenta_saldo_y_entrega_la_diferencia(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(2)->toDateString(), 'saltar_domingos' => false]);
        app(PagoService::class)->aplicar($p, 84000, today()->toDateString(), $u->id); // 7 de 10 → saldo 36.000

        $r = app(PrestamoService::class)->renovar($p->fresh(), [
            'monto' => 120000, 'valor_cuota' => 15000, 'num_cuotas' => 8, 'fecha_inicio' => today()->addDay()->toDateString(),
        ], $u->id);

        $this->assertSame(36000.0, $r['saldo']);
        $this->assertSame(84000.0, $r['entregar']); // 120.000 − 36.000
        $this->assertSame('pagado', $p->fresh()->estado);
        $this->assertSame(0.0, round($p->fresh()->saldo, 2));
        $this->assertSame('activo', $r['prestamo']->estado);

        $pago = $p->pagos()->where('renovacion', true)->firstOrFail();
        $this->assertSame(36000.0, (float) $pago->valor);

        // El pago de renovación no cuenta como efectivo cobrado hoy
        $this->actingAs($u)->get(route('hoy'))->assertOk();
        $cobradoHoy = \App\Models\Pago::where('renovacion', false)->whereDate('fecha', today())->sum('valor');
        $this->assertSame(84000.0, (float) $cobradoHoy);

        // ...ni se puede deshacer a mano
        $this->actingAs($u)->delete(route('pagos.destroy', $pago), ['motivo' => 'Prueba de motivo'])->assertStatus(422);
    }

    public function test_renovar_exige_cubrir_el_saldo_pendiente(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);

        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 2000, 'valor_cuota' => 1000, 'num_cuotas' => 2, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('valor_cuota');

        $this->assertSame('activo', $p->fresh()->estado);
        $this->assertSame(1, Prestamo::count());
    }

    public function test_caja_con_renovacion_solo_sale_la_diferencia(): void
    {
        $u = $this->cobrador(120000);
        $p = $this->prestamo($u); // presta 120.000 → caja 0
        app(PagoService::class)->aplicar($p, 84000, today()->toDateString(), $u->id); // caja 84.000

        app(PrestamoService::class)->renovar($p->fresh(), [
            'monto' => 120000, 'valor_cuota' => 18000, 'num_cuotas' => 8, 'fecha_inicio' => today()->addDay()->toDateString(), // debe 144.000
        ], $u->id);

        // 120k base + 84k cobrado + 36k renovación − 120k − 120k nuevo = 0 → entregó 84.000 en efectivo
        $this->assertSame(0.0, app(\App\Services\CajaService::class)->caja($u->id));
    }

    public function test_cobrador_ajeno_no_puede_renovar(): void
    {
        $a = $this->cobrador();
        $b = $this->cobrador();
        $p = $this->prestamo($a);
        app(PagoService::class)->aplicar($p, 120000, today()->toDateString(), $a->id);

        $this->actingAs($b)->post(route('prestamos.renovar', $p->id), [
            'valor_cuota' => 1000, 'num_cuotas' => 2, 'fecha_inicio' => today()->toDateString(),
        ])->assertNotFound();
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

        $s->deshacer($ultimo, 'Error de digitación');

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
        $this->assertSame(40000.0, $caja->caja($u->id)); // 150k - 120k + 20k - 10k

        $this->expectException(ValidationException::class);
        $this->prestamo($u, ['valor_cuota' => 8000]); // 80.000 > caja restante
    }

    public function test_anular_devuelve_el_monto_a_la_caja(): void
    {
        $u = $this->cobrador(120000);
        $p = $this->prestamo($u);
        $caja = app(\App\Services\CajaService::class);
        $this->assertSame(0.0, $caja->caja($u->id));

        app(PrestamoService::class)->anular($p);

        $this->assertSame(120000.0, $caja->caja($u->id));
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

    public function test_con_la_base_apagada_no_se_valida_ni_se_muestra_caja(): void
    {
        config(['cobros.usar_base' => false]);
        $u = $this->cobrador(0); // sin base entregada

        $p = $this->prestamo($u, ['valor_cuota' => 500000]); // 5.000.000 sin base: se puede
        $this->assertSame(10, $p->cuotas()->count());

        $this->actingAs($u)->get(route('hoy'))->assertOk()->assertDontSee('Caja disponible');
        $this->actingAs($u)->get(route('prestamos.create'))->assertOk()->assertDontSee('Caja disponible');
        $this->actingAs($u)->get(route('gastos.index'))->assertOk()->assertDontSee('Tu caja actual');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('bases.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('resumen'))->assertOk()->assertDontSee('>Caja<', false);
    }

    public function test_formulario_crea_prestamo_por_cuota_sin_descripcion_ni_monto_total(): void
    {
        $u = $this->cobrador();
        $cliente = Cliente::create(['user_id' => $u->id, 'nombres' => 'Carla']);
        $base = ['cliente_id' => $cliente->id, 'num_cuotas' => 4, 'frecuencia' => 'semanal', 'fecha_inicio' => today()->addDay()->toDateString()];

        $this->actingAs($u)->post(route('prestamos.store'), $base + ['valor_cuota' => 25000])->assertRedirect();
        $this->actingAs($u)->post(route('prestamos.store'), $base)->assertSessionHasErrors('valor_cuota');

        $this->assertSame([100000.0], Prestamo::pluck('monto_total')->map(fn ($v) => (float) $v)->all());
        $this->actingAs($u)->get(route('prestamos.create'))->assertOk()
            ->assertDontSee('name="descripcion"', false)
            ->assertDontSee('name="monto_total"', false)
            ->assertDontSee('Calcular por');
    }

    public function test_listado_muestra_en_que_cuota_va(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(3)->toDateString(), 'saltar_domingos' => false]);
        app(PagoService::class)->aplicar($p, 24000, today()->toDateString(), $u->id); // 2 cuotas

        $this->assertSame(3, Prestamo::conTotales()->find($p->id)->cuota_actual);
        $this->actingAs($u)->get(route('prestamos.index'))->assertSee('Cuota 3/10');
        $this->actingAs($u)->get(route('hoy'))->assertSee('Cuota 3/10');
    }

    public function test_detalle_del_prestamo_carga_en_todos_los_estados(): void
    {
        $u = $this->cobrador();
        $pagos = app(PagoService::class);

        $activo = $this->prestamo($u, ['fecha_inicio' => today()->subDays(4)->toDateString(), 'saltar_domingos' => false]);
        $pagos->aplicar($activo, 30000, today()->toDateString(), $u->id);

        $pagado = $this->prestamo($u);
        $pagos->aplicar($pagado, 120000, today()->toDateString(), $u->id);

        $anulado = $this->prestamo($u);
        app(PrestamoService::class)->anular($anulado);

        $renovado = app(PrestamoService::class)->renovar($activo->fresh(), [
            'monto' => 120000, 'valor_cuota' => 20000, 'num_cuotas' => 6, 'fecha_inicio' => today()->addDay()->toDateString(),
        ], $u->id)['prestamo'];

        foreach ([$activo, $pagado, $anulado, $renovado] as $p) {
            $this->actingAs($u)->get(route('prestamos.show', $p->id))->assertOk();
        }
        $this->actingAs($u)->get(route('prestamos.show', $activo->id))->assertSee('Renovación (sin efectivo)');
        $this->actingAs($u)->get(route('prestamos.show', $renovado->id))->assertSee('Renovación del préstamo');
        $this->actingAs($u)->get(route('prestamos.index', ['estado' => 'anulado']))->assertOk();
        $this->actingAs($u)->get(route('prestamos.index', ['estado' => 'todos']))->assertOk();
    }

    public function test_ordenar_ruta_guarda_el_orden_y_hoy_lo_respeta(): void
    {
        $u = $this->cobrador();
        $ids = [];
        foreach (['Ana', 'Beto', 'Carla'] as $n) {
            $c = Cliente::create(['user_id' => $u->id, 'nombres' => $n]);
            $this->prestamo($u, ['cliente_id' => $c->id, 'fecha_inicio' => today()->toDateString(), 'saltar_domingos' => false]);
            $ids[$n] = $c->id;
        }

        $this->actingAs($u)->get(route('ruta.index'))->assertOk()->assertSee('Ordenar ruta');

        $this->actingAs($u)->postJson(route('ruta.guardar'), ['orden' => [$ids['Carla'], $ids['Ana'], $ids['Beto']]])->assertOk();

        $this->assertSame([1, 2, 3], [Cliente::find($ids['Carla'])->orden, Cliente::find($ids['Ana'])->orden, Cliente::find($ids['Beto'])->orden]);
        $this->actingAs($u)->get(route('hoy'))->assertSeeInOrder(['Carla', 'Ana', 'Beto']);
    }

    public function test_ordenar_ruta_no_permite_clientes_de_otro_cobrador(): void
    {
        $a = $this->cobrador();
        $b = $this->cobrador();
        $propio = Cliente::create(['user_id' => $a->id, 'nombres' => 'Propio']);
        $ajeno = Cliente::create(['user_id' => $b->id, 'nombres' => 'Ajeno', 'orden' => 7]);

        $this->actingAs($a)->postJson(route('ruta.guardar'), ['orden' => [$propio->id, $ajeno->id]])->assertForbidden();
        $this->assertSame(7, $ajeno->fresh()->orden);

        // Si manda el user_id de otro, se ignora: solo puede tocar su propia ruta
        $this->actingAs($a)->postJson(route('ruta.guardar'), ['user_id' => $b->id, 'orden' => [$propio->id]])->assertOk();
        $this->assertSame(1, $propio->fresh()->orden);
        $this->assertSame(7, $ajeno->fresh()->orden);
    }

    public function test_cliente_nuevo_queda_al_final_de_la_ruta(): void
    {
        $u = $this->cobrador();
        $this->actingAs($u)->post(route('clientes.store'), ['nombres' => 'Primero']);
        $this->actingAs($u)->post(route('clientes.store'), ['nombres' => 'Segundo']);

        $this->assertSame([1, 2], Cliente::orderBy('id')->pluck('orden')->all());
    }

    public function test_deshacer_pago_exige_motivo_y_deja_auditoria(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        $pago = app(PagoService::class)->aplicar($p, 36000, today()->toDateString(), $u->id);

        // Sin motivo (o muy corto) no se deshace
        $this->actingAs($u)->delete(route('pagos.destroy', $pago))->assertSessionHasErrors('motivo');
        $this->actingAs($u)->delete(route('pagos.destroy', $pago), ['motivo' => 'ab'])->assertSessionHasErrors('motivo');
        $this->assertDatabaseHas('pagos', ['id' => $pago->id]);

        $this->actingAs($u)->delete(route('pagos.destroy', $pago), ['motivo' => 'El cliente no pagó, lo anoté por error'])->assertRedirect();

        $this->assertDatabaseMissing('pagos', ['id' => $pago->id]);
        $a = \App\Models\Auditoria::where('accion', 'pago.deshecho')->firstOrFail();
        $this->assertSame($u->id, $a->user_id);
        $this->assertSame($p->id, $a->prestamo_id);
        $this->assertSame('El cliente no pagó, lo anoté por error', $a->motivo);
        $this->assertSame(36000.0, (float) $a->antes['valor']);
        $this->assertSame(84000.0, (float) $a->despues['saldo_antes']);
        $this->assertSame(120000.0, (float) $a->despues['saldo_despues']);
    }

    public function test_mover_fecha_y_anular_exigen_motivo_y_quedan_registrados(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['saltar_domingos' => false, 'fecha_inicio' => today()->addDays(5)->toDateString()]);
        $cuota = $p->cuotas()->first();
        $nueva = today()->addDays(20)->toDateString();

        $this->actingAs($u)->patch(route('cuotas.fecha', $cuota), ['fecha' => $nueva])->assertSessionHasErrors('motivo');
        $this->assertNotSame($nueva, $cuota->fresh()->fecha->toDateString());

        $this->actingAs($u)->patch(route('cuotas.fecha', $cuota), ['fecha' => $nueva, 'motivo' => 'Cliente viaja esa semana'])->assertRedirect();
        $a = \App\Models\Auditoria::where('accion', 'cuota.fecha_movida')->firstOrFail();
        $this->assertSame($cuota->fecha->toDateString(), $a->antes['fecha']);
        $this->assertSame($nueva, $a->despues['fecha']);
        $this->assertSame('Cliente viaja esa semana', $a->motivo);

        $this->actingAs($u)->post(route('prestamos.anular', $p->id))->assertSessionHasErrors('motivo');
        $this->assertSame('activo', $p->fresh()->estado);

        $this->actingAs($u)->post(route('prestamos.anular', $p->id), ['motivo' => 'Se creó al cliente equivocado'])->assertRedirect();
        $this->assertDatabaseHas('auditorias', ['accion' => 'prestamo.anulado', 'prestamo_id' => $p->id, 'motivo' => 'Se creó al cliente equivocado']);
    }

    public function test_gasto_y_cliente_eliminados_exigen_motivo(): void
    {
        $u = $this->cobrador();
        $g = Gasto::create(['user_id' => $u->id, 'monto' => 5000, 'descripcion' => 'Almuerzo', 'fecha' => today()]);
        $c = Cliente::create(['user_id' => $u->id, 'nombres' => 'Zoe']);

        $this->actingAs($u)->delete(route('gastos.destroy', $g))->assertSessionHasErrors('motivo');
        $this->actingAs($u)->delete(route('clientes.destroy', $c))->assertSessionHasErrors('motivo');
        $this->assertNotNull($g->fresh());

        $this->actingAs($u)->delete(route('gastos.destroy', $g), ['motivo' => 'Gasto duplicado'])->assertRedirect();
        $this->actingAs($u)->delete(route('clientes.destroy', $c), ['motivo' => 'Cliente repetido'])->assertRedirect();

        $this->assertSoftDeleted('gastos', ['id' => $g->id]);
        $this->assertSoftDeleted('clientes', ['id' => $c->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'gasto.eliminado', 'motivo' => 'Gasto duplicado', 'user_id' => $u->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'cliente.eliminado', 'motivo' => 'Cliente repetido', 'user_id' => $u->id]);
    }

    public function test_el_historial_se_ve_en_el_prestamo_y_solo_gestores_ven_la_auditoria_general(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        $pago = app(PagoService::class)->aplicar($p, 12000, today()->toDateString(), $u->id);
        $this->actingAs($u)->delete(route('pagos.destroy', $pago), ['motivo' => 'Pago duplicado por error']);

        $this->actingAs($u)->get(route('prestamos.show', $p->id))->assertOk()
            ->assertSee('Historial de cambios')->assertSee('Pago deshecho')->assertSee('Pago duplicado por error');

        $this->actingAs($u)->get(route('auditoria.index'))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('auditoria.index'))->assertOk()->assertSee('Pago duplicado por error');
    }

    public function test_la_auditoria_no_se_puede_editar_ni_borrar(): void
    {
        $a = app(\App\Services\AuditoriaService::class)->registrar('pago.registrado', 'Prueba');

        $this->expectException(\LogicException::class);
        $a->update(['resumen' => 'Alterado']);
    }

    public function test_la_auditoria_no_se_puede_borrar(): void
    {
        $a = app(\App\Services\AuditoriaService::class)->registrar('pago.registrado', 'Prueba');

        $this->expectException(\LogicException::class);
        $a->delete();
    }

    public function test_empresa_solo_ve_la_auditoria_de_su_empresa(): void
    {
        $mio = $this->cobrador();
        $ajeno = User::factory()->create(['role' => 'cobrador', 'empresa_id' => 2]);
        $empresa = User::factory()->create(['role' => 'empresa', 'empresa_id' => 1]);

        $this->actingAs($mio);
        app(\App\Services\AuditoriaService::class)->registrar('gasto.editado', 'Cambio del cobrador propio');
        $this->actingAs($ajeno);
        app(\App\Services\AuditoriaService::class)->registrar('gasto.editado', 'Cambio de otra empresa');

        $this->actingAs($empresa)->get(route('auditoria.index'))->assertOk()
            ->assertSee('Cambio del cobrador propio')->assertDontSee('Cambio de otra empresa');
    }

    public function test_el_historial_de_pagos_muestra_la_hora_del_registro(): void
    {
        $this->travelTo(now()->setTime(15, 42));
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        app(PagoService::class)->aplicar($p, 12000, today()->toDateString(), $u->id);

        $this->actingAs($u)->get(route('prestamos.show', $p->id))->assertOk()->assertSee('03:42 pm');
    }

    public function test_renovar_monto_prestado_resta_el_saldo_y_el_total_sale_de_cuota_por_cuotas(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(2)->toDateString(), 'saltar_domingos' => false]);
        app(PagoService::class)->aplicar($p, 84000, today()->toDateString(), $u->id); // saldo 36.000

        // Se presta 100.000 (entrega 64.000) y el cliente debe 10 cuotas de 15.000 = 150.000
        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 100000, 'valor_cuota' => 15000, 'num_cuotas' => 10, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertRedirect()->assertSessionHas('ok');

        $nuevo = Prestamo::where('renovado_de_id', $p->id)->firstOrFail();
        $this->assertSame(100000.0, (float) $nuevo->monto);        // lo prestado
        $this->assertSame(150000.0, (float) $nuevo->monto_total);  // lo que debe el cliente
        $this->assertSame(10, $nuevo->cuotas()->count());
        $this->assertSame(150000.0, (float) $nuevo->cuotas()->sum('valor'));
        $this->assertSame(150000.0, round($nuevo->fresh()->saldo, 2));
        $this->assertSame(36000.0, (float) $p->pagos()->where('renovacion', true)->sum('valor'));
        $this->assertStringContainsString('$64000', session('ok'));

        $this->actingAs($u)->get(route('prestamos.show', $nuevo->id))->assertOk()
            ->assertSee('Prestado:')->assertSee('Debe:');
    }

    public function test_renovar_exige_que_el_monto_prestado_cubra_el_saldo_aunque_el_total_sea_mayor(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u); // saldo 120.000

        // Total a pagar 300.000, pero solo se presta 50.000: no cubre el saldo
        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 50000, 'valor_cuota' => 30000, 'num_cuotas' => 10, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('valor_cuota');

        $this->assertSame(1, Prestamo::count());
        $this->assertSame('activo', $p->fresh()->estado);
    }

    public function test_renovar_no_ajusta_cuotas_el_total_es_exactamente_cuota_por_cuotas(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u);
        app(PagoService::class)->aplicar($p, 120000, today()->toDateString(), $u->id);

        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 90000, 'valor_cuota' => 33333, 'num_cuotas' => 3, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertRedirect();

        $nuevo = Prestamo::where('renovado_de_id', $p->id)->firstOrFail();
        $this->assertSame(99999.0, (float) $nuevo->monto_total);
        $this->assertSame([33333.0, 33333.0, 33333.0], $nuevo->cuotas->map(fn ($c) => (float) $c->valor)->all());
    }

    public function test_el_monto_a_prestar_no_puede_superar_el_total_que_debe_el_cliente(): void
    {
        $u = $this->cobrador();
        $p = $this->prestamo($u, ['fecha_inicio' => today()->subDays(2)->toDateString(), 'saltar_domingos' => false]);
        app(PagoService::class)->aplicar($p, 84000, today()->toDateString(), $u->id); // saldo 36.000

        // Presta 200.000 pero el cliente solo debe 10 × 15.000 = 150.000 → rechazado, sin efectos
        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 200000, 'valor_cuota' => 15000, 'num_cuotas' => 10, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('monto');

        $this->assertSame(1, Prestamo::count());
        $this->assertSame('activo', $p->fresh()->estado);
        $this->assertSame(0, $p->pagos()->where('renovacion', true)->count());

        // Igual al total sí se permite (no gana nada, pero no supera)
        $this->actingAs($u)->post(route('prestamos.renovar', $p->id), [
            'monto' => 150000, 'valor_cuota' => 15000, 'num_cuotas' => 10, 'fecha_inicio' => today()->addDay()->toDateString(),
        ])->assertRedirect();
        $this->assertSame(2, Prestamo::count());
    }

    public function test_crear_un_prestamo_con_monto_mayor_al_total_se_rechaza_en_el_servicio(): void
    {
        $u = $this->cobrador();
        $cliente = Cliente::create(['user_id' => $u->id, 'nombres' => 'Eva']);

        $this->expectException(ValidationException::class);
        app(PrestamoService::class)->crear([
            'user_id' => $u->id, 'cliente_id' => $cliente->id, 'monto' => 500000,
            'valor_cuota' => 10000, 'num_cuotas' => 10, 'frecuencia' => 'semanal', 'fecha_inicio' => today()->toDateString(),
        ]);
    }
}
