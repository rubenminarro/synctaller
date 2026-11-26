# Tests

## Ejecutar

Desde la raíz:

```bash
npm test               # backend y frontend
npm run lint           # Pint (--test) y ESLint
npm run build          # tsc -b y build del frontend
```

Backend, desde `backend/`:

```bash
php artisan test                                  # suite completa, unos 6 minutos
php artisan test tests/Feature/Claims             # una carpeta
php artisan test --filter=test_nombre             # un test
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit   # si pdo_sqlite está deshabilitado
```

**No correr dos ejecuciones a la vez:** comparten `backend/database/testing.sqlite`.

Frontend, desde `frontend/`: `npm test`, `npm run lint` y `npm run build`.

La CI (`.github/workflows/ci.yml`) corre lo mismo en cada push y pull request, por separado para cada app y solo si cambió
su carpeta.

## Cómo está armada la suite

* SQLite en archivo (`database/testing.sqlite`): migra y siembra roles y permisos una vez por ejecución, y cada test corre en una
  transacción.
* Todo test corre en un taller de prueba (`Tests\TestCase`, historial 2.93): registrado en la base central
  (`database/testing-central.sqlite`, migrada una vez por ejecución) con el host de `APP_URL` y activado antes que
  RefreshDatabase. Los tests con varios talleres (`tests/Feature/Tenancy`) usan `Tests\Concerns\UsesTenantDatabases`, con
  una base extra por taller (`database/testing-tenant-{nombre}.sqlite`).
* No necesita red: `phpunit.xml` fija `EMAIL_DNS_CHECK=false`, y `uncompromised()` se simula con `Http::fake`.
* Cola `sync` y mail `array`; `Mail::fake()` para verificar envíos. Para probar el worker (historial 2.96), la cola
  `database` de la central, que comparte la transacción del test, y `queue:work --once` en el mismo proceso
  (`TenantQueueTest`). Si el test guarda en la cache entre talleres, también la cache `database` de la central: la
  `array` se vacía en cada cambio de taller (`ReminderCommandTest`, historial 2.97).

## Cobertura por carpeta

Contado el 2026-11-26: 605 tests (sin el `ExampleTest` de la plantilla, que se quitó al crear el monorepo). Las cifras
son métodos de test: PHPUnit informa 607 corridas, porque un test con data provider corre una vez por caso
(`TenantStorageTest`, desde T4). La relación con cada módulo está en [`modules/README.md`](../modules/README.md).

| Carpeta | Tests | Carpeta | Tests |
|---|---|---|---|
| `Access` | 19 | `Maintenance` | 7 |
| `Audit` | 6 | `MasterData` | 31 |
| `Auth` | 9 | `Notifications` | 17 |
| `Budgets` | 23 | `Parts` | 30 |
| `Claims` | 54 | `Payments` | 11 |
| `Clients` | 16 | `Preferences` | 7 |
| `Deliveries` | 10 | `Purchases` | 16 |
| `Documents` | 11 | `Receptions` | 39 |
| `Http` | 6 | `Reports` | 11 |
| `Insurers` | 7 | `Roles` | 2 |
| `Inventory` | 25 | `Seeders` | 6 |
| `Invoices` | 27 | `Services` | 23 |
| `Settings` | 32 | `Vehicles` | 6 |
| `WorkOrderParts` | 33 | `WorkOrders` | 19 |
| `Tenancy` | 102 | `Unit` | 1 |

## Criterios para tests nuevos

| Regla | Motivo |
|---|---|
| Permisos: probar con un rol propio | El administrador pasa todo por `Gate::before` y un nombre mal escrito no se nota |
| Un test debe fallar sin su corrección | Comprobarlo una vez. Dos tests de horario pasaban también con el error |
| Horarios: simular un instante absoluto en UTC | "Las 22:00" en la zona de la aplicación pasaría también con la zona equivocada |
| Archivos subidos: usar archivos reales | `UploadedFile::fake()` informa el tipo según la extensión |
| Nuevo estado en un enum: agregar su escenario | Y su verificación en `DatabaseSeederTest` |

## Lo que la suite no cubre

| Tema | Cómo se verifica |
|---|---|
| Concurrencia real | Solo en MySQL: `tests/Manual/document_sequence_concurrency.php` |
| Migraciones en MySQL | A mano: los nombres de índices largos no fallan en SQLite |
| Gráficos del frontend | Sin tests. Se revisan en un navegador |
