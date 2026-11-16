# Retoques pendientes

Lista de ajustes por hacer antes del congelamiento (`v1.0.0`). Se reciben hasta el **miércoles 18/11/2026**, se implementan el **19 y 20** (`v0.7.1`)
y se congela el 21 o el 22. Cada retoque se corrige con pruebas, suite completa y Pint; ninguno exige una sesión de carga (Constitución 2.2.0).

Prioridad: **Alta** (impide usar o expone datos), **Media** (molesta pero hay salida), **Baja** (mejora opcional).

## Plantilla

Copiar al final de «Pendientes» y numerar con el siguiente `R-nn`.

```markdown
### R-nn · Título corto
- **Pantalla**: ruta o nombre de la pantalla (y rol o permiso con el que se ve)
- **Qué se observa**:
- **Qué se espera**:
- **Prioridad**: Alta | Media | Baja
- **Estado**: pendiente
```

## Pendientes

### R-01 · Markdown sin interpretar en las respuestas del asistente (H1)
- **Pantalla**: `/asistente` (cualquier rol con `asistente.usar`)
- **Qué se observa**: las respuestas muestran el Markdown tal cual (asteriscos sueltos, p. ej. `**Tecnología**`).
- **Qué se espera**: texto legible. Renderizarlo de forma segura, escapando el HTML y sin enlaces peligrosos, o pedir al modelo texto sin formato. Con pruebas de que `<script>` y `javascript:` salen escapados.
- **Prioridad**: Media
- **Estado**: pendiente

### R-02 · Menú del Administrador desbordado en ventana angosta (H2)
- **Pantalla**: menú principal (Administrador, más de doce ítems)
- **Qué se observa**: con la ventana angosta el menú se desborda y los ítems quedan blancos sobre fondo claro.
- **Qué se espera**: ítems agrupados en secciones desplegables según los permisos del usuario, adaptable a pantallas pequeñas y sin nombres de rol en el código (SC-003 y SC-005). Revisar las pruebas de menú de los Módulos 1 y 2.
- **Prioridad**: Media
- **Estado**: pendiente

### R-03 · Pregunta de seguimiento sin nombre vuelve a pedir aclaración (H3)
- **Pantalla**: `/asistente` (apoderado con varios hijos)
- **Qué se observa**: tras preguntar por Luna, «¿Y en Matemática?» vuelve a pedir aclaración.
- **Qué se espera**: recordar al último estudiante mencionado dentro de la misma conversación, sin persistir nada. Un nombre en el mensaje manda sobre el recordado, y el alumno recordado se vuelve a validar contra los alumnos propios en cada mensaje (R-13).
- **Prioridad**: Baja (opcional)
- **Estado**: pendiente
