# Quickstart: validar el Asistente Virtual

## 1. Local (adaptador falso)

```bash
cp .env.example .env            # ASISTENTE_PROVEEDOR=falso
php artisan migrate:fresh --seed
vendor/bin/pest --filter=Asistente
```

Esperado: todas las pruebas del módulo en verde y **ninguna** llamada de red (`Http::preventStrayRequests()`).
Entrar como un usuario Padre de Familia o Alumno de los seeders demo y abrir `/asistente`.

## 2. Pruebas clave (todas con el adaptador falso)

| Prueba | Evidencia |
|---|---|
| Acceso por permiso | Padre/Alumno ven y usan; Docente/Director/Administrador reciben 403 y no ven el menú; asignar el permiso a otro rol lo habilita |
| Acceso ajeno | pedir datos de otro alumno ⇒ rechazo neutro; el payload del falso no contiene a ese alumno |
| No publicado | nota de un periodo sin cierre ⇒ no aparece en el payload |
| Minimización | el payload no contiene documento, fecha de nacimiento ni correo |
| Producción | `APP_ENV=production` + adaptador sin datos reales ⇒ mensaje claro, 0 llamadas |
| Límite | superar el límite ⇒ aviso con tiempo de espera, 0 llamadas |
| Proveedor caído | `ProveedorFalso::fallar()` / `Http::fake` 500 ⇒ mensaje comprensible |
| Español y aviso | el mensaje de sistema exige español; el aviso aparece en la vista |

## 3. Prueba de humo en staging (~10 min, datos ficticios)

Prerrequisitos: VM de staging en `v0.7.0`, `OPENROUTER_API_KEY` y `ASISTENTE_PROVEEDOR=openrouter` en el `.env`
del servidor, modelo gratuito y lista de respaldo configurados (ver `docs/despliegue/`). El permiso `asistente.usar` lo crea `sge:sincronizar-base` en el despliegue
(conteo de staging 32 = 31 del seeder + `reportes.ver` manual).

**Datos**: no se ejecuta `EvaluacionDemoSeeder` en staging; se usan los existentes. Antes de empezar, el responsable
(1) vuelve a cerrar el **I Bimestre de Primaria · 1.° grado A** con las notas de **Luna Demo 1** y
(2) vincula a **María Torres Gómez** también con Luna, de modo que tenga al menos dos hijos (el caso de varios hijos).
Se necesita además un Alumno (la propia Luna Demo 1 o su cuenta) y un rol sin el permiso (Docente, Director o Administrador).

1. Padre de Familia (María Torres Gómez) nombrando a **Luna Demo 1**: preguntar por el promedio de un curso del I Bimestre y por las faltas; comparar con «Mis notas»/«Estudiantes a mi cargo» y «Mi asistencia».
2. María Torres Gómez con varios hijos: preguntar «¿cómo van las notas?» sin nombrar hijo ⇒ pide aclaración.
3. Pedir las notas de un alumno ajeno (uno que no sea hijo de María) ⇒ rechazo neutro y sin datos del alumno (el tono lo decide el modelo; es la verificación manual de R-13).
4. Alumno (Luna Demo 1): preguntar «¿cómo voy?» ⇒ responde solo con sus propios datos. Comprobar el aviso de IA y que todas las respuestas estén en español.
5. Superar el límite por usuario ⇒ aviso con el tiempo de espera.
6. Docente/Director/Administrador: no ven «Asistente virtual».

Si algo falla por un modelo gratuito rotado, ajustar `ASISTENTE_OPENROUTER_MODELO` / `_RESPALDO` (sin cambios de código).
