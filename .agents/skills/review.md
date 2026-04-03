# Skill: Code Review — Laravel

## Inputs esperados
- `src/` o `/app` — código fuente Laravel a revisar

---

## Protocolo de ejecución

### Fase 1 — Reconocimiento del proyecto
1. Lee `composer.json` para identificar versión de Laravel, paquetes instalados
   y dependencias de desarrollo.
2. Lista la estructura de directorios: `app/`, `routes/`, `database/`, `config/`,
   `tests/` para entender la arquitectura usada.
3. Lee `routes/web.php` y `routes/api.php` para mapear los endpoints expuestos.
4. Revisa `app/Models/` para entender el modelo de datos.
5. Anota si el proyecto usa Repository Pattern, Actions, Services, o solo
   Controllers gordos (esto determina el nivel de deuda técnica base).

### Fase 2 — Revisión de seguridad (prioridad máxima)
6. Busca `DB::statement`, `DB::select` con interpolación de strings — SQL injection.
7. Revisa todos los modelos: ¿tienen `$fillable` o `$guarded` definido?
   Mass assignment sin protección es CRÍTICO.
8. Comprueba que los Form Requests existen para todas las rutas POST/PUT/PATCH.
   Validación en el Controller directamente es IMPORTANTE.
9. Verifica que las rutas sensibles tienen middleware `auth` o policies aplicadas.
10. Busca `{!! $variable !!}` en vistas Blade — XSS si la variable viene de usuario.
11. Confirma que `.env` no está commiteado y que `config()` se usa en vez de
    `env()` fuera de archivos de configuración.
12. Revisa permisos en Storage y Bootstrap/cache si hay configuración de servidor.

### Fase 3 — Revisión de correctitud y N+1 queries
13. Busca bucles que contengan queries Eloquent (`foreach` con `->find()`,
    `->where()`, relaciones accedidas sin eager loading). N+1 es IMPORTANTE.
14. Verifica que los Jobs implementan `ShouldQueue` correctamente y tienen
    `failed()` o `$tries` / `$backoff` definidos.
15. Comprueba que los eventos tienen sus listeners registrados en `EventServiceProvider`.
16. Revisa el manejo de excepciones: ¿hay try/catch donde se necesita?
    ¿El Handler de excepciones está bien configurado?

### Fase 4 — Revisión de arquitectura y calidad Laravel
17. Detecta Fat Controllers: métodos con más de 30 líneas merecen extraerse
    a un Service o Action. Anótalo como IMPORTANTE.
18. Verifica que las migraciones tienen método `down()` implementado.
    Migraciones sin rollback son IMPORTANTE.
19. Busca `dd()`, `dump()`, `var_dump()`, `print_r()` olvidados en el código.
    Es CRÍTICO si están en rutas de producción.
20. Revisa que los Resources/API Resources transforman correctamente los datos
    y no exponen campos sensibles (passwords, tokens).
21. Comprueba que las factories y seeders están definidas para los modelos
    principales (necesario para que @qa pueda crear datos de test).

### Fase 5 — Generación del informe
22. Crea `production_artifacts/review_report.md` con esta estructura:

---
# Code Review Report — Laravel

## Resumen ejecutivo
[2-3 frases: estado general, versión de Laravel detectada, nivel de riesgo,
recomendación de continuar o no al siguiente agente]

## Stack detectado
- Laravel: X.X
- PHP: X.X
- Paquetes clave: (lista de los más relevantes para el review)
- Patrón arquitectónico: (MVC clásico / Services / Actions / Repository)

## Hallazgos CRÍTICOS 🔴
(bloquean el merge)

### CR-001: [Título]
- **Archivo**: `ruta/archivo.php:línea`
- **Problema**: descripción clara
- **Impacto**: qué puede salir mal
- **Sugerencia Laravel**: ejemplo de corrección usando las herramientas del framework

## Hallazgos IMPORTANTES 🟡
(deben resolverse en este ciclo)

### IM-001: [Título]
...mismo formato...

## Sugerencias 🟢
(mejoras opcionales)

### SG-001: [Título]
...mismo formato...

## Métricas
- Archivos revisados: N
- Controladores analizados: N
- Modelos analizados: N
- Migraciones analizadas: N
- Tests existentes: N
- Hallazgos críticos: N | Importantes: N | Sugerencias: N
---

23. PAUSA. Notifica al usuario que el informe está listo.
24. NO continúes a @qa hasta recibir aprobación explícita del usuario.

## Regla de parada
Si encuentras mass assignment desprotegido, SQL injection directa, o secrets
hardcodeados en el código fuente, PARA inmediatamente y reporta solo esos
hallazgos. Son showstoppers que invalidan el resto del review.