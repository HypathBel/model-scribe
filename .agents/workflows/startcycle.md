# Workflow: /startcycle — Laravel

Ejecuta el pipeline completo de revisión, testing y deploy para proyectos Laravel.

## Secuencia

1. **@reviewer** — ejecuta `skills/review.md` sobre el código en `app_build/`
   - Lee la estructura del proyecto Laravel
   - Revisa seguridad, correctitud, arquitectura y calidad
   - Genera `production_artifacts/review_report.md`

2. **[PAUSA]** — espera aprobación del usuario sobre `review_report.md`
   - El usuario revisa los hallazgos
   - Si aprueba: continuar al paso 3
   - Si rechaza: el pipeline termina, el usuario corrige y relanza

3. **@qa** — ejecuta `skills/qa.md` usando el review aprobado
   - Prepara el entorno de testing Laravel
   - Escribe y ejecuta tests con PHPUnit / Pest
   - Corrige bugs encontrados
   - Genera `production_artifacts/test_results.md`

4. **[PAUSA]** — espera aprobación del usuario sobre `test_results.md`
   - El usuario revisa cobertura y bugs corregidos
   - Si aprueba: continuar al paso 5
   - Si rechaza: vuelve a @qa con instrucciones adicionales

5. **@devops** — ejecuta `skills/devops.md` para CI/CD
   - Genera el pipeline de GitHub Actions
   - Verifica y ejecuta el deploy al entorno objetivo
   - Genera `production_artifacts/deploy_status.md`

## Uso
```
/startcycle
```

## Variantes útiles
```
# Solo review, sin continuar al pipeline
@reviewer Revisa app_build/ con skills/review.md. Foco en seguridad.

# Solo QA sobre un módulo específico
@qa Testea únicamente app_build/app/Http/Controllers/AuthController.php

# Solo deploy a staging
@devops Despliega a staging usando skills/devops.md
```