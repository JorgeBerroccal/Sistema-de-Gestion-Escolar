# Asistente virtual en staging (Módulo 6)

Variables y pasos para que el asistente funcione en staging. Referencia funcional: [`docs/asistente.md`](../asistente.md).

## Variables del `.env` del servidor

`docker-compose.prod.yml` pasa a la aplicación una lista **explícita** de variables; las del asistente ya están en ella. Se escriben en
`/opt/sge/.env` (permisos 600), **nunca en el repositorio**:

```bash
ASISTENTE_PROVEEDOR=openrouter
OPENROUTER_API_KEY=<clave exclusiva de staging, con límite de gasto>
ASISTENTE_OPENROUTER_MODELO=<modelo gratuito vigente, p. ej. uno que termine en :free>
ASISTENTE_OPENROUTER_RESPALDO=<modelo1:free>,<modelo2:free>
```

Sin `ASISTENTE_PROVEEDOR=openrouter` el asistente usa el adaptador falso (responde texto de prueba). Sin la clave o sin el modelo, el usuario
ve que el asistente no está disponible. Los demás valores (límites, tiempo de espera) tienen valores por defecto y son opcionales.

> `deploy.sh` rechaza marcadores `<...>` fuera de comentarios: reemplázalos antes de aplicar.

Aplicar: editar el `.env`, ejecutar `./deploy/deploy.sh --reconfigurar` y comprobar `curl https://<fqdn>/up`.

## Permiso nuevo

`asistente.usar` lo crea y asigna a Padre de Familia y Alumno el despliegue (`sge:sincronizar-base`, ya parte de `deploy.sh`); no hay que
sembrarlo a mano. El conteo de permisos del seeder es **31**; en staging puede mostrar **32** por `reportes.ver`, creado a mano en el Módulo 1.

## Modelos gratuitos y datos reales

Con modelos gratuitos el adaptador declara que **no admite datos reales**: en staging (datos ficticios) funciona; con `APP_ENV=production`
el asistente se niega a funcionar. Para producción real hace falta un modelo de pago y `ASISTENTE_OPENROUTER_DATOS_REALES=true`.

## Si un modelo gratuito deja de funcionar

Cambiar `ASISTENTE_OPENROUTER_MODELO` y/o `ASISTENTE_OPENROUTER_RESPALDO` en el `.env` y repetir `--reconfigurar`. No requiere una versión nueva.
