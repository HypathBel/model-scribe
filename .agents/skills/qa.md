# Skill: QA Testing — Laravel

## Inputs esperados
- `app_build/` — código fuente Laravel
- `production_artifacts/review_report.md` — informe del @reviewer

---

## Protocolo de ejecución

### Fase 1 — Preparación del entorno de test
1. Lee `composer.json` — verifica si el proyecto usa PHPUnit o Pest.
   - PHPUnit: configuración en `phpunit.xml`
   - Pest: configuración en `pest.config.php` o `phpunit.xml` con plugin Pest
2. Verifica que existe `.env.testing` o que `phpunit.xml` define las variables
   de entorno necesarias (DB_CONNECTION=sqlite, etc.).
3. Si no existe `.env.testing`, créalo con:
   ```
   APP_ENV=testing
   DB_CONNECTION=sqlite
   DB_DATABASE=:memory:
   CACHE_DRIVER=array
   QUEUE_CONNECTION=sync
   MAIL_MAILER=array
   ```
4. Corre `composer install --dev` si faltan dependencias de test.
5. Lee `review_report.md` y extrae todos los hallazgos CRÍTICO e IMPORTANTE
   para priorizar qué testear primero.

### Fase 2 — Tests de Feature (endpoints HTTP)
6. Para cada ruta en `routes/web.php` y `routes/api.php` que tenga lógica
   de negocio, crea un Feature test en `tests/Feature/`.
7. Estructura base de un Feature test Laravel:
   ```php
   // PHPUnit
   public function test_usuario_autenticado_puede_crear_recurso(): void
   {
       $user = User::factory()->create();
       $response = $this->actingAs($user)
           ->postJson('/api/recursos', ['nombre' => 'Test']);
       $response->assertStatus(201)
           ->assertJsonStructure(['id', 'nombre']);
       $this->assertDatabaseHas('recursos', ['nombre' => 'Test']);
   }

   // Pest equivalente
   it('usuario autenticado puede crear recurso', function () {
       $user = User::factory()->create();
       actingAs($user)
           ->postJson('/api/recursos', ['nombre' => 'Test'])
           ->assertStatus(201)
           ->assertJsonStructure(['id', 'nombre']);
       assertDatabaseHas('recursos', ['nombre' => 'Test']);
   });
   ```
8. Cubre estos casos por endpoint:
   - Happy path (datos válidos, usuario con permisos).
   - Usuario no autenticado → debe recibir 401.
   - Usuario sin permisos → debe recibir 403.
   - Datos inválidos → debe recibir 422 con errores de validación.

### Fase 3 — Tests de validación (Form Requests)
9. Para cada Form Request en `app/Http/Requests/`, crea tests que verifiquen:
   - Que campos requeridos ausentes devuelven 422.
   - Que formatos incorrectos (email inválido, string donde se espera int) fallan.
   - Que el happy path pasa la validación.
   ```php
   public function test_crear_recurso_falla_sin_nombre(): void
   {
       $user = User::factory()->create();
       $this->actingAs($user)
           ->postJson('/api/recursos', [])
           ->assertStatus(422)
           ->assertJsonValidationErrors(['nombre']);
   }
   ```

### Fase 4 — Tests de modelos Eloquent
10. Para cada modelo con scopes, mutators, accessors o relaciones complejas,
    crea un Unit test en `tests/Unit/Models/`:
    ```php
    public function test_scope_activo_filtra_correctamente(): void
    {
        User::factory()->count(3)->create(['activo' => true]);
        User::factory()->count(2)->create(['activo' => false]);
        $this->assertCount(3, User::activo()->get());
    }
    ```
11. Verifica las relaciones principales con `assertRelation` o creando registros
    relacionados y comprobando que se recuperan correctamente.

### Fase 5 — Tests de Jobs y Events
12. Para cada Job en `app/Jobs/`, crea tests usando `Queue::fake()`:
    ```php
    public function test_job_se_despacha_al_crear_recurso(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/recursos', ['nombre' => 'Test']);
        Queue::assertPushed(ProcesarRecursoJob::class);
    }
    ```
13. Para Events, usa `Event::fake()` de forma similar.
14. Para Mails, usa `Mail::fake()` y `Mail::assertSent()`.

### Fase 6 — Tests de base de datos
15. Verifica que todas las migraciones corren sin errores:
    ```bash
    php artisan migrate:fresh --env=testing
    ```
16. Si hay seeders de test, verifica que corren sin errores:
    ```bash
    php artisan db:seed --class=TestingSeeder --env=testing
    ```

### Fase 7 — Ejecución y corrección
17. Ejecuta la suite completa:
    ```bash
    php artisan test
    # o con Pest:
    ./vendor/bin/pest
    # con cobertura (si está disponible Xdebug o PCOV):
    php artisan test --coverage
    ```
18. Por cada test fallido: analiza el error, corrige el bug en el código fuente
    y re-ejecuta ese test específico:
    ```bash
    php artisan test --filter NombreDelTest
    ```
19. Repite hasta que todos los tests pasen o hasta encontrar un bug CRÍTICO
    irresoluble.

### Fase 8 — Reporte
20. Genera `production_artifacts/test_results.md`:

---
# Test Results — Laravel

## Resumen
- Framework de test: PHPUnit X.X / Pest X.X
- Total tests: N
- Pasados: N ✅
- Fallidos: N ❌
- Omitidos: N ⏭️
- Cobertura estimada: N% (si disponible)

## Tests escritos en este ciclo
- Feature tests: N (lista de archivos creados)
- Unit tests: N (lista de archivos creados)

## Bugs corregidos
### BUG-001: [Título — referencia al hallazgo del review]
- **Archivo modificado**: `ruta/archivo.php`
- **Cambio realizado**: descripción del fix

## Bugs pendientes (si los hay)
### PENDIENTE-001: [Título]
- **Severidad**: CRÍTICO / IMPORTANTE
- **Motivo**: por qué no se pudo resolver
- **Recomendación**: qué debe hacer el equipo

## Comando para reproducir
```bash
php artisan test
```
---

21. PAUSA. Notifica al usuario que los resultados están listos.
22. NO continúes a @devops hasta recibir aprobación explícita del usuario.

## Regla de parada
Si hay tests fallidos relacionados con seguridad (autenticación, autorización,
validación) que no puedes corregir, PARA inmediatamente. No tiene sentido
desplegar código con vulnerabilidades confirmadas.