# 06 - Drivers arquitectónicos

> **Pregunta guía:** ¿Qué requisito o condición puede cambiar la forma en que diseñamos la arquitectura?

Se conservan los candidatos y los drivers DA01–DA09. La columna **Estado real** dice cómo se resolvió cada uno en el sistema construido.

## Evaluación de candidatos

| Fuente | Elemento | ¿Requiere una decisión de arquitectura? | ¿Es driver? |
|--------|----------|------------------------------------------|:-----------:|
| Requisito funcional | RF02/RF04 Control de acceso RBAC configurable | Sí: define un componente transversal que protege todos los módulos y un modelo de datos de roles y permisos. | **Sí** |
| Requisito funcional | RF19/RF20 Asistente virtual con IA | Sí: obliga a integrar un servicio externo y a filtrar los datos antes de enviarlos. | **Sí** |
| Requisito funcional | RF05–RF07 Estructura académica configurable | Sí: la estructura académica se modela como datos, no como código. | **Sí** |
| Requisito funcional | RF17 Boleta en PDF | En el diseño, sí: generar el PDF consume CPU y memoria y obligaba a una cola con worker. **En lo construido se decidió generarlo en la solicitud** (spec 006, R-13), porque la constitución no autoriza colas. | **Sí** (resuelto distinto) |
| Requisito funcional | RF13 Tipos de asistencia | No: es una regla de negocio interna del módulo de Asistencia (en lo construido, un catálogo en tabla). | No |
| Atributo de calidad | AC01/AC02 Seguridad y privacidad | Sí: afecta autenticación, autorización y la integración con IA. | **Sí** |
| Atributo de calidad | AC03 Rendimiento en cierre de bimestre | Sí: la meta de 1000 usuarios afecta el acceso a datos (índices, carga anticipada, paginación), agrega Redis para sesiones, exige PHP-FPM y condiciona el dimensionamiento del servidor. | **Sí** |
| Atributo de calidad | AC05 Mantenibilidad | Sí: define la organización por módulos y la dirección de las dependencias internas. | **Sí** |
| Atributo de calidad | AC07 Testeabilidad | Sí: exige separar la lógica de negocio en clases que puedan probarse de forma aislada. | **Sí** |
| Atributo de calidad | AC08 Usabilidad responsive | No: se resuelve en el diseño de la interfaz. | No |
| Restricción | RC03/RC04 Laravel + Blade/Livewire | Sí: determina un monolito modular sin frontend separado. | **Sí** |
| Restricción | RC08/RC09 Docker con nginx + PHP-FPM en una VM pequeña | Sí: limita la arquitectura a pocos contenedores y descarta microservicios. | **Sí** |
| Restricción | RC18 Asistente virtual fuera de la prueba de carga | Sí: el servicio de IA debe poder sustituirse sin tocar el resto del módulo. | **Sí** |
| Restricción | RC15 Ley de Protección de Datos Personales | Sí: refuerza la privacidad y el filtrado de datos hacia la IA. | **Sí** |
| Restricción | RC02 Uso de Git y GitHub | No: afecta el proceso, no la estructura del sistema. | No |

## Drivers arquitectónicos

| ID | Driver arquitectónico | Origen | ¿Por qué influye en la arquitectura? | Estado real |
|----|-----------------------|--------|--------------------------------------|-------------|
| **DA01** | El sistema debe controlar el acceso a cada funcionalidad mediante roles y permisos configurables (RBAC), sin permisos codificados de forma fija. | RF02, RF04, AC01 | Obliga a un componente transversal de autorización que usan todos los módulos y a un modelo de datos Rol–Permiso–Rol_Permiso. | **Atendido.** Tablas `roles`, `permisos`, `rol_permiso`; `Gate::before` y `can:` en rutas; **pertenencia fail-closed** para datos propios (ADR-003 y ADR-011). Sin `app/Policies`. |
| **DA02** | El asistente virtual debe responder solo con información autorizada para el usuario y enviar a la IA únicamente ese contexto. | RF19, RF20, AC02, RC07, RC15, RC18 | Define un servicio de orquestación que primero consulta los datos permitidos y luego llama al proveedor; el cliente queda detrás de una interfaz sustituible. | **Atendido.** `AsistenteService`, `ConstructorContexto` y el puerto `ProveedorIA` con `ProveedorFalso` y `ProveedorOpenRouter`; falla cerrado en producción si el adaptador no admite datos reales (ADR-004 modificada). |
| **DA03** | El sistema debe mantener tiempos de respuesta adecuados en el cierre de bimestre con 1000 usuarios virtuales simultáneos, sobre una VM de 2 vCPU. | RF17, AC03, AC04, RC08, RC09, RC18 | Provocó cuatro decisiones en el diseño: Redis, nginx + PHP-FPM, cola con worker para la boleta, e índices con carga anticipada y paginación. | **Parcial.** Sí: Redis (sesiones y limitadores), nginx + PHP-FPM con OPcache, índices, `with()` y paginación. **No: cola ni worker** (ADR-007 no implementada). La meta de p95 **no se cumplió** con 1000 usuarios en 2 vCPU (AC03). |
| **DA04** | La estructura académica (niveles, grados, secciones, cursos, periodos y escala) debe ser configurable por institución sin cambiar el código. | RF05–RF07, AC06, RC14 | Determina un modelo de datos genérico y un módulo de Gestión Académica del que dependen Matrícula, Asistencia y Evaluación. | **Atendido.** Entidades `Nivel`, `Grado`, `Seccion`, `Curso`, `AnioEscolar`, `PeriodoEvaluacion`, `EscalaCalificacion` (ADR-005); la escala incluye equivalentes y umbral como datos. |
| **DA05** | El sistema debe construirse como un monolito modular en Laravel con Blade/Livewire y MySQL. | RC03, RC04, RC05, RC10 | Fija el estilo en capas dentro de una sola aplicación, con separación por módulos en lugar de servicios distribuidos. | **Atendido.** Laravel 13, Livewire 4, MySQL 8, una sola imagen de aplicación (ADR-001). |
| **DA06** | El sistema debe desplegarse con Docker en infraestructura de bajo costo. | RC08, RC09, AC09 | Limita el número de componentes desplegables y descarta arquitecturas con muchos servicios. | **Atendido** con **cuatro** contenedores (`web`, `app`, `db`, `redis`), sin worker ni proxy aparte. Despliegue con imágenes de GHCR (ADR-009 modificada y ADR-013). |
| **DA07** | La lógica de negocio debe poder probarse de forma automatizada (unitarias, integración y carga) antes de cada despliegue. | AC07, RC11, RC12 | Exige separar la lógica en clases de servicio independientes de controladores y vistas. | **Atendido.** Servicios por dominio (`EvaluacionService`, `AsistenciaService`, `MatriculaService`, etc.) y clases puras (`CalculoPromedios`); el CI ejecuta Pest y Pint. |
| **DA08** | Las acciones críticas deben quedar auditadas. | RF22, AC10, RC15 | Añade un componente transversal de auditoría. | **No implementado.** No hay componente de auditoría; solo `registrado_por` y `cerrado_por` (ADR-010 no implementada). |
| **DA09** | El sistema debe permitir modificar o agregar funcionalidades sin afectar innecesariamente a otros módulos. | AC05 | Influye en la separación de responsabilidades y las dependencias: lleva a un patrón uniforme por módulo. | **Parcial.** Patrón uniforme por dominio (ADR-001 y ADR-002); no se midió con `arch()` ni con `git diff` (AC05). |

## Ejemplo de construcción de un driver

- **Requisito funcional:** el sistema debe ofrecer un asistente virtual que responda consultas sobre notas y asistencia.
- **Atributo de calidad:** el asistente no debe exponer datos de otros estudiantes.
- **Restricción:** el modelo de IA se consume a través de un puerto de proveedor con adaptadores intercambiables (constitución 2.3.0).
- **DRIVER ARQUITECTÓNICO (DA02):** seguridad e integración controlada del asistente virtual con IA.
