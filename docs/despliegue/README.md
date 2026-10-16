# Despliegue del SGE en staging

El SGE corre en una máquina virtual Ubuntu de **Azure for Students** (región Chile Central) como entorno
**staging** (`APP_ENV=staging`): HTTPS con Let's Encrypt sobre el nombre DNS `*.cloudapp.azure.com`, la
misma pila de Docker Compose del desarrollo (nginx, PHP-FPM, MySQL, Redis) con la configuración de
producción de la constitución (OPcache sin validar marcas de tiempo y `php artisan optimize` en cada
arranque), imágenes construidas por GitHub Actions y publicadas en GHCR. No hay datos reales de ningún
colegio. La prueba de carga completa solo se ejecuta aquí, nunca en producción.

Última revisión de la documentación: 16/10/2026 (versiones publicadas `v0.2.0` y `v0.2.1`).

| Quiero… | Documento |
|---|---|
| Crear los recursos en el portal de Azure (región, VM, disco, IP, red, SSH) | [aprovisionamiento-azure.md](aprovisionamiento-azure.md) |
| Dejar el servidor listo y hacer el primer despliegue (incluye hacer públicas las imágenes de GHCR) | [primer-despliegue.md](primer-despliegue.md) |
| Desplegar una versión nueva o volver a la anterior | [desplegar-y-volver-atras.md](desplegar-y-volver-atras.md) |
| Entender el CI (Pest + Pint) y exigirlo antes de integrar | [integracion-continua.md](integracion-continua.md) |
| Renovar o diagnosticar el certificado HTTPS | [certificado.md](certificado.md) |
| Respaldar y restaurar la base de datos | [respaldo-y-restauracion.md](respaldo-y-restauracion.md) |
| Ver el costo, cambiar el tamaño de la VM, configurar alertas, detener la VM | [costo-y-tamano.md](costo-y-tamano.md) |
| Ejecutar la prueba de carga de 1000 usuarios (lista de verificación que termina en B2ats_v2) | [prueba-de-carga.md](prueba-de-carga.md) |

Diseño y decisiones: [`specs/002-despliegue-ci-staging/`](../../specs/002-despliegue-ci-staging/).
Evidencias: `specs/001-auth-roles-rbac/evidencias/` (Módulo 1) y `specs/002-despliegue-ci-staging/evidencias/`.

## Reglas que no se saltan

- Los secretos (`APP_KEY`, contraseñas de base de datos, `SGE_ADMIN_PASSWORD`) viven **solo** en
  `/opt/sge/.env` del servidor (permisos 600), nunca en el repositorio ni en las imágenes.
- El servidor **nunca construye** imágenes (tiene 1 GB de RAM): descarga las de GHCR por etiqueta.
- La prueba de carga termina siempre volviendo a **B2ats_v2**; sin `metrics.sh cierre` →
  `evidencia_completa: true` la evidencia no cuenta.
- Solo están abiertos los puertos 22 (solo clave + fail2ban), 80 y 443; MySQL y Redis no se publican.
