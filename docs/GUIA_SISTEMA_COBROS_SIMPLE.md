# Sistema de Cobros SIMPLE — Guía de construcción

> Documento para cargar en el nuevo subdominio y construir desde cero una versión **mucho más sencilla**
> del sistema actual (`coll_system`, módulo V2). Está escrito para que Claude Code (u otro desarrollador)
> lo lea y construya el proyecto sin necesitar acceso al sistema viejo.

---

## 1. Objetivo

Sistema para que un **cobrador** gestione préstamos (de dinero, muebles, mercancía) con cobro en cuotas
y registre pagos desde el celular. El sistema actual es complejo; el nuevo debe ser **lo mínimo necesario**.

### Qué se queda (núcleo)
1. Login y 3 roles.
2. Clientes (CRUD).
3. Préstamos (crear → genera cuotas automáticamente).
4. **Cobro del día**: lista de cuotas que toca cobrar hoy + botón "Pagar".
5. Registrar pago / abono.
6. Lista de atrasados.
7. **Gastos** del cobrador (registro simple de egresos).
8. **Base de dinero**: efectivo con el que cuenta cada cobrador para prestar; al prestar se valida contra la caja disponible.
9. Resumen simple (cobrado hoy, por cobrar hoy, total atrasado, caja disponible).

### Qué se elimina respecto al sistema viejo
| Se elimina | Por qué |
|---|---|
| Calendario de cuotas y cambio/deshacer fechas masivo | Complejo; mover fecha manual en una cuota basta |
| Festivos Argentina / tabla `festivos_extra` / reglas especiales de diciembre | Solo opción "saltar domingos" |
| GPS tracking y lat/long en pagos | No esencial |
| Refinanciación | Se hace anulando y creando otro préstamo |
| Portal público de clientes | Fase 2 opcional |
| Estado `T` (saldada total), tablas `rol`, `menu`, `menu_rol`, `usuario_rol` | Roles como columna; menú fijo |
| Score/calificación del cliente | Fase 2 opcional |
| Matriz de ~12 casos en `PagoController@guardar` | Se reemplaza por **una regla simple** (ver §5) |
| PWA, empleados separados de usuarios | Usuario = persona |

---

## 2. Stack recomendado

- **Laravel 11** + PHP 8.2+ (verificar la versión de PHP del subdominio; si es < 8.2 usar Laravel 10).
- MySQL.
- Auth: **Laravel Breeze** (Blade) — login/logout ya resueltos.
- Front: Blade + **Bootstrap 5** + un poco de JS vanilla/fetch. Sin DataTables, sin jQuery obligatorio.
  Diseño **mobile-first** (el cobrador usa celular): tarjetas grandes, botones grandes.
- Sin librerías extra de permisos: un middleware propio de rol.

Instalación base:
```bash
composer create-project laravel/laravel cobros
cd cobros
composer require laravel/breeze --dev
php artisan breeze:install blade
# configurar .env (DB_*, APP_URL, APP_TIMEZONE=America/Bogota u otra)
php artisan migrate
```

---

## 3. Roles y alcance de datos

Columna `role` en `users`: `admin`, `empresa`, `cobrador`.

| role | Ve |
|---|---|
| `admin` | Todo |
| `empresa` | Los datos de todos los cobradores de su empresa (`empresa_id`) |
| `cobrador` | Solo lo suyo (`user_id` propio) |

Patrón único (en un trait `ScopesByUser` usado por todos los controllers):

```php
// Devuelve los ids de usuario cuyos datos puede ver el usuario actual
protected function userIds(): array {
    $u = auth()->user();
    if ($u->role === 'admin')   return User::pluck('id')->all();
    if ($u->role === 'empresa') return User::where('empresa_id', $u->empresa_id)->pluck('id')->all();
    return [$u->id];
}
// Uso: Prestamo::whereIn('user_id', $this->userIds())
```
Siempre filtrar con esto en **toda** consulta y en toda ruta con `{id}` (evita ver datos ajenos cambiando el id en la URL).

---

## 4. Modelo de datos (7 tablas)

> En el sistema viejo había 12 tablas y PKs raras (`idp`, `idd`, `ide`). Aquí todo usa `id`.

### `users` (extiende la de Breeze)
`id, name, email, password, role (admin|empresa|cobrador), empresa_id (nullable, int), activo (bool)`

### `clientes`
`id, user_id (cobrador dueño), nombres, apellidos, documento (string), telefono, direccion, barrio, observacion, orden (int, para ordenar la ruta de cobro), activo (bool), timestamps, softDeletes`

### `prestamos`
| campo | tipo | nota |
|---|---|---|
| id | pk | |
| user_id | fk users | cobrador |
| cliente_id | fk clientes | |
| descripcion | string null | "Nevera Samsung", "Efectivo"... |
| monto | decimal(12,2) | capital entregado / valor del producto |
| interes_pct | decimal(5,2) | % total (ej. 20) |
| monto_total | decimal(12,2) | monto + interés (calculado al crear) |
| num_cuotas | int | |
| valor_cuota | decimal(12,2) | monto_total / num_cuotas (redondeado; última cuota ajusta) |
| frecuencia | enum | `diario`, `semanal`, `quincenal`, `mensual` |
| saltar_domingos | bool | solo aplica a diario |
| fecha_inicio | date | fecha de la 1ª cuota |
| estado | enum | `activo`, `pagado`, `anulado` |
| timestamps, softDeletes | | |

> `saldo` y `atrasado` **no se guardan**: se calculan (ver §5). Así nunca quedan inconsistentes
> (en el sistema viejo `monto_pendiente`, `monto_atrasado`, `cuotas_atrasadas` se actualizaban a mano y se descuadraban).

### `cuotas`
`id, prestamo_id, numero (int), fecha (date), valor (decimal), pagado (decimal, default 0), estado (pendiente|pagada), timestamps`
- `estado` solo 2 valores. "Atrasada" = `estado='pendiente' AND fecha < hoy` (derivado, no guardado).
- Índices: `(prestamo_id, numero)`, `(fecha, estado)`.

### `gastos`
`id, user_id (cobrador), monto (decimal), descripcion (string 150), fecha (date, default hoy), timestamps, softDeletes`
- Cada cobrador ve/edita los suyos; `empresa` y `admin` ven los de su alcance (§3).

### `bases` (entradas de dinero a la caja del cobrador)
`id, user_id (cobrador que recibe), monto (decimal), fecha (date), nota (string null), creado_por (fk users), timestamps`
- Cada fila es una entrega de dinero al cobrador para prestar (base inicial o recargas). Solo `admin`/`empresa` pueden crearlas.
- Si un cobrador devuelve efectivo, se registra una fila con monto negativo.

### `pagos`
`id, prestamo_id, cuota_id (nullable), user_id (quien cobró), valor (decimal), fecha (date), nota, timestamps`
- Es el **historial/recibo**. Cada vez que el cobrador cobra, se inserta una fila. Para deshacer un pago: borrarlo y recalcular las cuotas (ver §5).

---

## 5. Reglas de negocio

### 5.1 Crear préstamo → generar cuotas
Entradas: cliente, monto, interes_pct, num_cuotas, frecuencia, fecha_inicio, saltar_domingos.
```
monto_total = monto * (1 + interes_pct/100)
valor_cuota = round(monto_total / num_cuotas)     // la última cuota = monto_total - suma de anteriores
```
Fechas (en una transacción `DB::transaction`):
```
fecha = fecha_inicio  (si saltar_domingos y es domingo → +1 día)
para i = 1..num_cuotas:
    crear cuota(numero=i, fecha, valor)
    fecha += { diario: 1 día, semanal: 7, quincenal: 15, mensual: 1 mes (Carbon addMonthNoOverflow) }
    si frecuencia=diario y saltar_domingos y fecha es domingo → fecha += 1 día
```
(Sin festivos ni reglas de diciembre. Si luego se quieren festivos, agregar tabla `festivos` y un `while` extra.)

### 5.2 Registrar pago (UNA sola regla, reemplaza la matriz del sistema viejo)
Entrada: `prestamo_id`, `valor`, `fecha` (default hoy), `cuota_id` opcional.

Algoritmo "cascada" — el dinero se aplica a las cuotas pendientes **de la más antigua a la más nueva**:
```
DB::transaction:
  crear fila en pagos
  restante = valor
  por cada cuota pendiente del préstamo ORDER BY numero:
      falta = cuota.valor - cuota.pagado
      aplica = min(restante, falta)
      cuota.pagado += aplica
      si cuota.pagado >= cuota.valor → cuota.estado = 'pagada'
      restante -= aplica
      si restante <= 0 → break
  si restante > 0 → rechazar (error: "el pago excede el saldo") — validar ANTES de guardar
  si no quedan cuotas pendientes → prestamo.estado = 'pagado'
```
Esto cubre automáticamente: pago exacto, abono parcial, pago de cuota atrasada, adelanto de cuotas
y pago total del saldo, sin casos especiales.

### 5.3 Valores calculados (nunca guardados)
```
saldo           = SUM(cuotas.valor - cuotas.pagado)                   del préstamo
atrasado        = SUM(valor - pagado) WHERE estado='pendiente' AND fecha < hoy
cuotas_atrasadas= COUNT(...) mismas condiciones
por_cobrar_hoy  = SUM(valor - pagado) WHERE estado='pendiente' AND fecha <= hoy   (incluye atrasadas)
```
Poner estos métodos como accessors/scopes en el modelo `Prestamo` para reutilizarlos en todas las vistas.

### 5.4 Deshacer un pago
Borrar la fila de `pagos` y **reconstruir** las cuotas del préstamo: poner `pagado=0, estado=pendiente` en todas
y re-aplicar en cascada todos los pagos restantes ordenados por fecha. (Simple y siempre consistente.)
Recalcular `prestamos.estado` (si había quedado `pagado`, volver a `activo`).

### 5.5 Caja / base de dinero
La caja de un cobrador **se calcula**, no se guarda:
```
caja = SUM(bases.monto)            // dinero entregado para prestar
     + SUM(pagos.valor)            // lo cobrado
     - SUM(prestamos.monto)        // lo prestado (estado != anulado)
     - SUM(gastos.monto)           // gastos
```
- **Al crear un préstamo**: si `prestamos.monto > caja` → rechazar con mensaje "Base insuficiente: disponible $X".
  (Opción en `config/cobros.php`: `'bloquear_sin_base' => true|false`; en `false` solo muestra advertencia.)
- Se muestra la **caja disponible** arriba en la pantalla de nuevo préstamo, en "Cobro de hoy" y en el resumen.
- Un préstamo de producto (mueble): `monto` = costo del producto para el negocio; descuenta de la caja igual que el efectivo.
- Al anular un préstamo, su `monto` vuelve a la caja (ya no se suma). Al deshacer un pago, el cobro se resta (fila borrada).
- Gasto: validar que no deje la caja negativa solo como advertencia (no bloquear).

### 5.6 Gastos
CRUD simple: monto, descripción, fecha. Total de gastos del día/mes aparece en el resumen y descuenta de la caja (§5.5).

### 5.7 Anular préstamo
`estado='anulado'` + softDelete. No aparece en cobros. Solo admin/empresa (o el dueño si no tiene pagos).

### 5.8 Mover fecha de una cuota
Edición simple de `cuotas.fecha` de una cuota pendiente (opcional: "mover esta y las siguientes N días").

---

## 6. Pantallas (todas mobile-first)

| Ruta | Pantalla | Contenido |
|---|---|---|
| `/login` | Login | Breeze |
| `/` | **Cobro de hoy** (pantalla principal) | Totales arriba (por cobrar hoy / cobrado hoy). Lista de tarjetas, una por préstamo con cuota ≤ hoy pendiente: nombre cliente, dirección, teléfono (link `tel:` y WhatsApp), valor a cobrar hoy, atraso si lo hay. Botón grande **Cobrar** → modal con valor (precargado con lo que toca) y nota. Orden = `clientes.orden`. Buscador por nombre. |
| `/atrasados` | Atrasados | Préstamos con `atrasado > 0`, ordenados por mayor atraso |
| `/clientes` | Clientes | Tabla/lista con búsqueda; crear/editar en modal o página simple |
| `/prestamos` | Préstamos | Lista con filtro activo/pagado/anulado; botón "Nuevo préstamo" |
| `/prestamos/crear` | Formulario | Campos de §5.1 + **vista previa** (valor cuota, total, fecha final) calculada en JS antes de guardar |
| `/prestamos/{id}` | Detalle | Datos, tabla de cuotas con estado/colores, historial de pagos con botón deshacer, botón anular |
| `/gastos` | Gastos | Lista del mes con total; botón "Nuevo gasto" (modal: monto, descripción, fecha); editar/eliminar |
| `/bases` | Base de dinero (admin/empresa) | Por cobrador: base entregada, prestado, cobrado, gastos y **caja disponible**; botón "Entregar base" (cobrador, monto, fecha, nota) y historial |
| `/resumen` | Resumen (admin/empresa) | Cobrado por día / por cobrador, total en la calle, total atrasado, gastos, caja por cobrador |
| `/usuarios` | Usuarios (admin/empresa) | Crear cobradores |

Colores de cuota: pendiente = gris, pagada = verde, atrasada = rojo, parcial = amarillo.
Menú: barra inferior fija en móvil (Hoy · Atrasados · Clientes · Préstamos · Más → Gastos/Base/Resumen).

---

## 7. Estructura de código sugerida

```
app/
  Models/            User, Cliente, Prestamo, Cuota, Pago, Gasto, Base
  Http/Controllers/  CobroController (hoy, atrasados), ClienteController, PrestamoController,
                     PagoController (store, destroy), GastoController, BaseController, ResumenController, UsuarioController
  Http/Middleware/   RoleMiddleware  (->middleware('role:admin,empresa'))
  Services/          PrestamoService (generarCuotas), PagoService (aplicarPago, deshacerPago, reconstruir), CajaService (caja($userId))
  Concerns/          ScopesByUser (trait con userIds())
resources/views/     layouts/app, cobro/hoy, cobro/atrasados, clientes/*, prestamos/*, resumen
routes/web.php       todo bajo middleware ['auth'] (+ role donde aplique)
```
**La lógica de negocio va en `Services/`** (no en controllers de 1200 líneas como en el sistema viejo).
Los controllers solo validan, llaman al service y devuelven respuesta (JSON para el modal de cobro, Blade para el resto).

### Rutas
```php
Route::middleware('auth')->group(function () {
    Route::get('/', [CobroController::class, 'hoy']);
    Route::get('/atrasados', [CobroController::class, 'atrasados']);
    Route::resource('clientes', ClienteController::class)->except('show');
    Route::resource('prestamos', PrestamoController::class)->only(['index','create','store','show']);
    Route::post('prestamos/{prestamo}/anular', [PrestamoController::class, 'anular']);
    Route::post('prestamos/{prestamo}/pagos', [PagoController::class, 'store']);
    Route::delete('pagos/{pago}', [PagoController::class, 'destroy']);
    Route::patch('cuotas/{cuota}/fecha', [PrestamoController::class, 'moverCuota']);
    Route::resource('gastos', GastoController::class)->except('show');
    Route::middleware('role:admin,empresa')->group(function () {
        Route::get('/bases', [BaseController::class, 'index']);
        Route::post('/bases', [BaseController::class, 'store']);
        Route::get('/resumen', [ResumenController::class, 'index']);
        Route::resource('usuarios', UsuarioController::class)->except('show');
    });
});
```

---

## 8. Plan de construcción por fases (pedir a Claude Code de a una)

1. **Base**: proyecto Laravel + Breeze, columnas `role/empresa_id/activo` en users, middleware de rol, layout mobile con menú inferior.
2. **Clientes**: migración, modelo, CRUD, búsqueda, trait de alcance.
3. **Préstamos**: migraciones `prestamos` + `cuotas`, `PrestamoService::generarCuotas`, formulario con vista previa, listado y detalle.
3b. **Gastos y base**: migraciones `gastos` + `bases`, `CajaService`, CRUD de gastos, pantalla de bases, validación de caja al crear préstamo.
4. **Pagos**: migración `pagos`, `PagoService` (cascada + deshacer + reconstruir), modal de cobrar.
5. **Cobro de hoy + Atrasados**: pantalla principal con totales.
6. **Resumen y usuarios**.
7. **Pruebas** (ver §9) y despliegue.
8. *(Fase 2 opcional)* portal del cliente, calificación, GPS, WhatsApp de recordatorio.

---

## 9. Pruebas mínimas (PHPUnit Feature tests)

- Generar cuotas: diario con saltar domingos no produce fechas en domingo; mensual suma 1 mes; suma de cuotas = `monto_total`.
- Pago exacto marca la cuota `pagada`; abono parcial deja `pendiente` con `pagado` parcial.
- Pago mayor a una cuota cubre la siguiente (cascada).
- Pago > saldo es rechazado.
- Pagar todo el saldo → préstamo `pagado`.
- Deshacer pago restaura cuotas y estado `activo`.
- Caja: `bases + cobrado - prestado - gastos` es correcta; crear préstamo con monto > caja es rechazado (con `bloquear_sin_base=true`); anular préstamo devuelve el monto a la caja.
- Gastos: un cobrador no ve ni edita los de otro.
- Un cobrador **no** puede ver ni pagar préstamos de otro (403/404).

---

## 10. Despliegue en el subdominio

1. Apuntar el document root del subdominio a `public/` del proyecto (o subir el proyecto un nivel arriba de `public_html` y copiar `public/` allí ajustando rutas en `index.php`).
2. `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, credenciales DB propias (**base de datos nueva, separada de la del sistema actual**).
3. `composer install --no-dev --optimize-autoloader`, `php artisan key:generate`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`.
4. Crear el primer usuario `admin` con un seeder.

---

## 11. Migración de datos del sistema viejo (opcional)

Mapa si se quieren traer datos reales:

| Viejo | Nuevo |
|---|---|
| `usuario` + `empleado` | `users` (name = empleado.nombres+apellidos, role según `rol_id`: 1→admin, 2→empresa, 3→cobrador, `empresa_id` = empleado.empresa_id) |
| `cliente` | `clientes` (`consecutivo` → `orden`) |
| `prestamo` (`idp`) | `prestamos` (`tipo_pago` Diario/Semanal/Quincenal/Mensual → `frecuencia`; `estado` P→pagado, A→anulado, vacío→activo) |
| `detalle_prestamo` (`idd`) | `cuotas` (`d_numero_cuota`→numero, `valor_cuota`→valor, `valor_cuota_pagada`→pagado, estado P/T→pagada, C/A→pendiente) |
| `pago` | `pagos` |
| `gasto` (`idg`, `monto`, `descripcion`, `usuario_id`) | `gastos` (fecha = created_at) |
| `empleado.base` | una fila en `bases` por cobrador con ese monto (fecha = día de migración, nota "Base inicial migrada") |

Aviso: en el viejo `valor_cuota` de una cuota con abono anticipado se *reducía* (se descontaba el abono); al migrar,
sumar de nuevo `valor_cuota_pagada` para recuperar el valor original. Revisar con un préstamo de muestra antes de migrar todo.

---

## 12. Reglas para quien construya esto

- Mantener el código **simple**: si una función necesita más de ~40 líneas o más de 3 casos especiales, replantear.
- Nada de valores calculados guardados (`saldo`, `atrasado`): siempre derivar de `cuotas`.
- Todo dinero con `decimal`, nunca float.
- Toda operación que toque varias tablas va en `DB::transaction`.
- Todo texto de UI en español.
