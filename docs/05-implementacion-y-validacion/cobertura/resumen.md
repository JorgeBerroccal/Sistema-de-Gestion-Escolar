# Cobertura de pruebas (M-02)

Cobertura de **líneas ejecutables** de `app/`, medida con PCOV sobre la suite completa de Pest. Meta de diseño (EQ-07): cobertura de la capa de servicios ≥ 80 %. No es cobertura de la calidad de las aserciones.

## Resultado (20/11/2026, local, 951 pruebas en verde)

| Carpeta | Archivos | Líneas | Cubiertas | Cobertura |
|---|---:|---:|---:|---:|
| `app/Academico/` | 4 | 152 | 151 | 99,3 % |
| `app/Matricula/` | 7 | 174 | 157 | 90,2 % |
| `app/Asistencia/` | 3 | 133 | 133 | 100,0 % |
| `app/Evaluacion/` | 5 | 361 | 357 | 98,9 % |
| `app/Asistente/` | 13 | 180 | 177 | 98,3 % |
| `app/Autorizacion/` | 11 | 183 | 178 | 97,3 % |
| **Capa de servicios** | 43 | 1183 | 1153 | **97,5 %** |

Todo `app/`: 96,0 % (el informe de Pest da 95,9 % por el redondeo de su método). Fuente: [`docs/retoques.md`](../../retoques.md), M-02.

## Cobertura en el CI

Desde este cambio el job `ci / pruebas` mide la cobertura con PCOV en cada push y pull request y publica el informe como el artefacto **`cobertura`** (`cobertura.txt` y `cobertura-html/`, 90 días). No hay umbral: informa y no bloquea.

- Ejecución que generó el reporte: [https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45248630708](https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar/actions/runs/45248630708): 951 pruebas en verde y **95,98 % de líneas en todo `app/`** (2481 de 2585), job `cobertura`.
- El artefacto se descarga desde la sección **Artifacts** de esa ejecución; el HTML no se guarda en el repositorio.

El texto del CI da la cobertura **por clase y total**; la tabla por carpeta de arriba es la de M-02 (suma de las clases de cada carpeta) y se regenera con el procedimiento de abajo.

## Regenerarlo en local

La imagen de desarrollo no trae PCOV. Instalarlo de forma temporal en el contenedor (se pierde al recrearlo; no toca la imagen ni el repositorio):

```bash
docker compose exec app sh -c 'pecl install pcov'
docker compose exec app php -d extension=pcov.so -d pcov.enabled=1 \
  vendor/bin/pest --ci --colors=never --coverage-text=cobertura.txt --coverage-html=cobertura-html
```

Genera `cobertura.txt` y `cobertura-html/` en la raíz del proyecto (ignorados por Git). Para la tabla por carpeta, sumar las líneas ejecutables y cubiertas de las clases de cada carpeta de `app/` (el informe `clover`, `--coverage-clover=cobertura.xml`, las trae por archivo).
