# Contrato: Mecanismo de filtros de pertenencia (punto de extensión)

**Feature**: `001-auth-roles-rbac`

Contrato interno que usarán los módulos 2–6. Este módulo lo implementa y lo prueba con un modelo y
una regla de prueba; no registra reglas reales.

## Piezas

| Pieza | Ubicación | Responsabilidad |
|---|---|---|
| `ReglaPertenencia` (interfaz) | `app/Autorizacion/Pertenencia/` | Define qué registros de un modelo puede ver un usuario. |
| `RequierePertenencia` (trait) | `app/Autorizacion/Pertenencia/` | Declara que un modelo requiere pertenencia y expone la API de uso. |
| `RegistroReglasPertenencia` | `app/Autorizacion/Pertenencia/` | Singleton que resuelve la regla de un modelo desde la configuración. |
| `ReglaPertenenciaFaltante` | `app/Autorizacion/Pertenencia/` | Excepción; extiende `AccessDeniedHttpException` → HTTP 403. |
| `config/pertenencia.php` | `config/` | `'reglas' => [Modelo::class => Regla::class]`. |

## Interfaz `ReglaPertenencia`

```php
interface ReglaPertenencia
{
    /** Restringe una consulta a los registros visibles para el usuario. */
    public function aplicar(Builder $query, Usuario $usuario): Builder;

    /** Indica si el usuario puede acceder a un registro concreto. */
    public function permite(Usuario $usuario, Model $registro): bool;
}
```

Restricciones para quien implemente una regla:

- MUST decidir según el usuario y sus relaciones con los datos; MUST NOT comparar el nombre del rol.
- Si la visibilidad varía según la capacidad del usuario (p. ej. un Director ve todo), MUST
  expresarse con permisos (`$usuario->tienePermiso('matriculas.ver_todas')`), no con roles.

## API del trait `RequierePertenencia`

| Método | Comportamiento |
|---|---|
| `Modelo::query()->visiblePara($usuario)` (scope) | Aplica la regla registrada. **Sin regla → lanza `ReglaPertenenciaFaltante` (403).** |
| `$registro->autorizarPertenencia($usuario)` | Si `permite()` es falso → 403. **Sin regla → 403.** |

## Comportamiento (fail-closed)

| Situación | Resultado |
|---|---|
| Modelo sin el trait | No aplica pertenencia; solo RBAC. |
| Modelo con el trait y regla registrada | Listados filtrados; acceso a registro ajeno → 403. |
| Modelo con el trait y regla registrada, usuario sin registros relacionados | Listado vacío (no es error). |
| Modelo con el trait **sin** regla registrada | 403 en toda consulta y acceso; nunca se devuelven datos sin filtrar. |

## Orden de verificación en los módulos futuros

1. RBAC: `authorize('<recurso>.<accion>')` → 403 si el rol no tiene el permiso.
2. Pertenencia: `visiblePara()` / `autorizarPertenencia()` → filtra o 403.

El chatbot (módulo 6) MUST construir su contexto usando `visiblePara()` en cada consulta.

## Pruebas que acompañan este contrato (FR-028)

Con un modelo `RegistroDePrueba` (tabla creada solo en la prueba) y una `ReglaDePrueba` que permite
los registros cuyo `usuario_id` coincide con el usuario:

1. Con regla: `visiblePara()` devuelve solo los registros del usuario.
2. Con regla: `autorizarPertenencia()` sobre un registro ajeno → 403.
3. Sin regla registrada: `visiblePara()` → 403.
4. Modelo sin el trait: la consulta no se altera.
