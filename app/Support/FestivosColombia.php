<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Festivos de Colombia (Ley Emiliani), calculados: no hay tabla que mantener.
 *  - Fijos: 1 ene, 1 may, 20 jul, 7 ago, 8 dic, 25 dic.
 *  - Trasladados al lunes siguiente: 6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov, 11 nov.
 *  - Según Pascua: Jueves y Viernes Santo (fijos); Ascensión, Corpus Christi y Sagrado Corazón (lunes).
 */
class FestivosColombia
{
    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> fecha (Y-m-d) => nombre */
    public static function deAnio(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }

        $f = [];
        $fijo = function (int $m, int $d, string $n) use (&$f, $anio) {
            $f[Carbon::create($anio, $m, $d)->toDateString()] = $n;
        };
        $lunes = function (Carbon $fecha, string $n) use (&$f) {
            $f[self::alLunes($fecha)->toDateString()] = $n;
        };

        $fijo(1, 1, 'Año Nuevo');
        $fijo(5, 1, 'Día del Trabajo');
        $fijo(7, 20, 'Día de la Independencia');
        $fijo(8, 7, 'Batalla de Boyacá');
        $fijo(12, 8, 'Inmaculada Concepción');
        $fijo(12, 25, 'Navidad');

        $lunes(Carbon::create($anio, 1, 6), 'Reyes Magos');
        $lunes(Carbon::create($anio, 3, 19), 'San José');
        $lunes(Carbon::create($anio, 6, 29), 'San Pedro y San Pablo');
        $lunes(Carbon::create($anio, 8, 15), 'Asunción de la Virgen');
        $lunes(Carbon::create($anio, 10, 12), 'Día de la Raza');
        $lunes(Carbon::create($anio, 11, 1), 'Todos los Santos');
        $lunes(Carbon::create($anio, 11, 11), 'Independencia de Cartagena');

        $pascua = self::pascua($anio);
        $f[$pascua->copy()->subDays(3)->toDateString()] = 'Jueves Santo';
        $f[$pascua->copy()->subDays(2)->toDateString()] = 'Viernes Santo';
        $f[$pascua->copy()->addDays(43)->toDateString()] = 'Ascensión del Señor';
        $f[$pascua->copy()->addDays(64)->toDateString()] = 'Corpus Christi';
        $f[$pascua->copy()->addDays(71)->toDateString()] = 'Sagrado Corazón';

        ksort($f);

        return self::$cache[$anio] = $f;
    }

    public static function esFestivo(CarbonInterface $fecha): bool
    {
        return isset(self::deAnio($fecha->year)[$fecha->toDateString()]);
    }

    /** Fechas festivas de varios años (para el simulador del formulario). */
    public static function rango(int $desde, int $hasta): array
    {
        $r = [];
        for ($a = $desde; $a <= $hasta; $a++) {
            $r = array_merge($r, array_keys(self::deAnio($a)));
        }

        return $r;
    }

    /** Si no cae lunes, pasa al lunes siguiente. */
    private static function alLunes(Carbon $f): Carbon
    {
        return $f->isMonday() ? $f : $f->next(Carbon::MONDAY);
    }

    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher). */
    private static function pascua(int $y): Carbon
    {
        $a = $y % 19;
        $b = intdiv($y, 100);
        $c = $y % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $g = intdiv(8 * $b + 13, 25);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($y, $mes, $dia)->startOfDay();
    }
}
