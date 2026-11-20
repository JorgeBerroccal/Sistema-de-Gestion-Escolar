# 01 - Actores del sistema

> **Pregunta guía:** ¿Quién o qué interactúa con el sistema desde fuera para realizar una acción o intercambiar información?

## Contexto

```
                    SISTEMA DE GESTIÓN ESCOLAR (SGE)
                                  │
     ┌─────────────┬──────────────┼──────────────┬──────────────┐
     ↓             ↓              ↓              ↓              ↓
Administrador   Director       Docente     Padre de Familia   Alumno
```

```
                    SISTEMA DE GESTIÓN ESCOLAR (SGE)
                                  │
                                  ↓
                  OpenRouter (proveedor de IA detrás del
                  puerto ProveedorIA; modelo por entorno)
```

## Actores humanos

Los cinco roles son **datos** (tabla `roles`, cargados por `RolesYPermisosSeeder`). El código no compara nombres de rol: reconoce capacidades por permiso (constitución, Principio III; prueba `tests/Feature/Autorizacion/SinCondicionalesDeRolTest.php`).

| Actor | ¿Qué necesita realizar? | Cómo lo hace el sistema construido | Estado real |
|-------|-------------------------|-------------------------------------|-------------|
| **Administrador del Sistema** | Configurar la institución (nombre, logo, niveles, año escolar, periodos y escala de calificación), registrar grados, secciones y cursos, asignar docentes, gestionar usuarios, roles y permisos, y matricular estudiantes. | Hace todo lo anterior salvo el **logo**, que no existe. Además toma y corrige asistencia en cualquier sección, registra o corrige notas aunque el periodo esté cerrado, y cierra y reabre periodos por sección (`notas.cerrar_periodo`). No crea roles nuevos desde la interfaz (spec 001, supuestos). | **Parcial** (sin logo). |
| **Director** | Supervisar la gestión académica: consultar reportes consolidados de matrícula, asistencia y rendimiento; apoyar el proceso de matrícula. | **Solo consulta**: estructura académica, alumnos y nóminas, reporte de asistencia por sección, reporte de notas por sección y boletas de cualquier alumno. No modifica nada (spec 004, FR-024; spec 005, FR-012). No ve Usuarios, Roles ni Permisos (retoque R-06, se asignan solo al Administrador por defecto). No apoya la matrícula: solo la consulta. | **Parcial** (sin consolidado institucional). |
| **Docente** | Registrar la asistencia diaria y las calificaciones por competencia de los cursos y secciones que tiene asignados, y consultar reportes de sus estudiantes. | Toma la asistencia **por sección** y registra **notas por criterios de evaluación** solo donde tiene una asignación (specs 005 y 006); ve la nómina y los reportes de sus secciones. No accede a la boleta completa (spec 006, R-06). | **Modificado** (por sección y por criterios, no por curso ni competencia). |
| **Padre de Familia (Apoderado)** | Consultar la asistencia y las notas de su(s) hijo(s), descargar la boleta de calificaciones en PDF y hacer consultas al asistente virtual. | Ve solo a los alumnos vinculados a su cuenta; las notas y la boleta, solo de periodos cerrados y publicados; usa el asistente (`asistente.usar`). | **Cumplido.** |
| **Alumno** | Consultar sus propias notas y asistencia, descargar su boleta y hacer consultas al asistente virtual. | Ve su ficha vinculada, sus notas publicadas, su asistencia, su boleta y el asistente. | **Cumplido.** |

Quién es alumno, apoderado o docente se define por permisos marcadores (`matricula.ser_alumno`, `matricula.ser_apoderado`, `docencia.dictar`), no por el nombre del rol (specs 003 y 004).

## Sistemas externos

| Actor | ¿Qué necesita realizar? | Estado real |
|-------|-------------------------|-------------|
| **OpenRouter** (API compatible con OpenAI) | Recibir la pregunta del usuario junto con el contexto de datos autorizado y devolver una respuesta en lenguaje natural para el asistente virtual (Módulo 6). El modelo se elige por variable de entorno; en staging se usa un modelo gratuito. | **Cumplido** como adaptador `ProveedorOpenRouter` del puerto `ProveedorIA` (spec 007, `docs/asistente.md`). Un modelo de Claude solo se usaría a través de OpenRouter; el adaptador de Gemini (plan B) está documentado y **no implementado**. |

> **Nota:** el SGE no integra pasarela de pago, servicio de envío ni facturación: la gestión financiera está **fuera del alcance** (RC17).

## Infraestructura (no son actores del dominio)

Estas piezas participan en la construcción y el despliegue, no en los casos de uso de los usuarios:

| Pieza | Función | Referencia |
|-------|---------|------------|
| GitHub Actions y GHCR | Integración continua (Pest y Pint), construcción y publicación de las imágenes de producción por etiqueta | `docs/despliegue/integracion-continua.md`; ADR-013 |
| Azure (VM Ubuntu de Azure for Students) | Servidor de staging con HTTPS | `docs/despliegue/aprovisionamiento-azure.md`; ADR-009 |
| Let's Encrypt (certbot en el servidor) | Certificado HTTPS | `docs/despliegue/certificado.md` |

## Resumen

| Tipo | Actores |
|------|---------|
| Humanos | Administrador, Director, Docente, Padre de Familia, Alumno |
| Sistemas externos | OpenRouter |
| Infraestructura | GitHub Actions/GHCR, Azure, Let's Encrypt |
