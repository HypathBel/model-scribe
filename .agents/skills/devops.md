# Skill: DevOps / CI·CD — Laravel

## Inputs esperados
- `app_build/` — código fuente Laravel validado
- `production_artifacts/test_results.md` — resultados de QA

---

## Protocolo de ejecución

### Fase 0 — Verificación de prerequisitos (OBLIGATORIA)
1. Lee `production_artifacts/test_results.md`.
   Si reporta tests fallidos → PARA. No continúes hasta que @qa los resuelva.
2. Verifica que no hay hallazgos CRÍTICOS abiertos en `review_report.md`.
3. Identifica el entorno objetivo: staging / producción.
4. Confirma con el usuario antes de proceder a producción.

### Fase 1 — Generación del pipeline GitHub Actions
5. Crea `.github/workflows/laravel.yml` con este pipeline:

```yaml
name: Laravel CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    name: Tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.2', '8.3']

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: laravel_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, bcmath, pdo_mysql, redis
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Copy .env
        run: cp .env.example .env.testing

      - name: Generate app key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        run: php artisan migrate --env=testing --force
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: laravel_test
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Run Pint (lint)
        run: ./vendor/bin/pint --test

      - name: Run tests
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: laravel_test
          DB_USERNAME: root
          DB_PASSWORD: password

  deploy-staging:
    name: Deploy to Staging
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/develop' && github.event_name == 'push'
    environment: staging

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/staging
            git pull origin develop
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan event:cache
            php artisan queue:restart
            php artisan horizon:terminate
            echo "✅ Deploy staging completado: $(date)"

  deploy-production:
    name: Deploy to Production
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    environment: production

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Deploy via SSH (zero-downtime)
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/production

            # Modo mantenimiento
            php artisan down --retry=60 --secret="${{ secrets.MAINTENANCE_TOKEN }}"

            git pull origin main
            composer install --no-dev --optimize-autoloader

            # Migraciones (solo si no son destructivas)
            php artisan migrate --force

            # Rebuild caché de producción
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan event:cache
            php artisan icons:cache 2>/dev/null || true

            # Reiniciar workers
            php artisan queue:restart
            php artisan horizon:terminate 2>/dev/null || true

            # Salir de mantenimiento
            php artisan up

            echo "✅ Deploy producción completado: $(date)"
```

### Fase 2 — Secrets necesarios en GitHub
6. Documenta en `production_artifacts/deploy_status.md` qué secrets hay que
   configurar en GitHub → Settings → Secrets and variables → Actions:

| Secret | Descripción |
|---|---|
| `STAGING_HOST` | IP o dominio del servidor staging |
| `STAGING_USER` | Usuario SSH staging |
| `STAGING_SSH_KEY` | Clave privada SSH staging |
| `PROD_HOST` | IP o dominio del servidor producción |
| `PROD_USER` | Usuario SSH producción |
| `PROD_SSH_KEY` | Clave privada SSH producción |
| `MAINTENANCE_TOKEN` | Token secreto para acceder en modo mantenimiento |

### Fase 3 — Verificación local antes de push
7. Corre el linter Pint localmente:
   ```bash
   ./vendor/bin/pint --test
   ```
8. Corre los tests con cobertura:
   ```bash
   php artisan test --coverage
   ```
9. Verifica que no hay migraciones pendientes sin commitear:
   ```bash
   php artisan migrate:status
   ```
10. Verifica que `.env.example` está actualizado con todas las variables nuevas.

### Fase 4 — Comandos post-deploy (checklist)
Tras cada deploy exitoso, estos comandos deben haberse ejecutado en el servidor:
```bash
php artisan migrate --force          # migraciones pendientes
php artisan config:cache             # cachear configuración
php artisan route:cache              # cachear rutas
php artisan view:cache               # cachear vistas Blade
php artisan event:cache              # cachear listeners
php artisan queue:restart            # reiniciar workers de cola
php artisan horizon:terminate        # reiniciar Horizon (si se usa)
php artisan up                       # salir de mantenimiento
```

### Fase 5 — Reporte final
11. Genera `production_artifacts/deploy_status.md`:

---
# Deploy Status

## Pipeline
- Entorno: staging / producción
- Branch: [nombre]
- Commit: [hash]
- Fecha: [timestamp]

## Resultado
- Lint (Pint): ✅ / ❌
- Tests: ✅ N pasados / ❌ N fallidos
- Migraciones: ✅ ejecutadas / ⚠️ pendientes
- Caché reconstruida: ✅
- Queue workers reiniciados: ✅
- Estado final: ✅ DESPLEGADO / ❌ FALLIDO

## URL del entorno
- Staging: https://staging.tuapp.com
- Producción: https://tuapp.com

## Secrets pendientes de configurar en GitHub
[lista si aplica]

## Notas
[cualquier observación relevante del deploy]
---

## Regla de parada
NUNCA ejecutes `php artisan migrate --force` en producción si alguna migración
contiene `dropColumn`, `dropTable` o `truncate` sin confirmación explícita
del usuario. Los datos eliminados no se recuperan.