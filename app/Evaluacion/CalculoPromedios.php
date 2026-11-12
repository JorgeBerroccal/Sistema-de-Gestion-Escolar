<?php

namespace App\Evaluacion;

use App\Models\EscalaCalificacion;
use App\Support\Formato;

/**
 * Cálculo de notas y promedios (research R-04), puro: sin base de datos y sin nombrar escalas concretas.
 *
 * La escala es literal cuando define `equivalentes` (letra → número, configuración de la institución, no una
 * regla del Currículo Nacional) y numérica (rango de valores) cuando no. Una celda vacía es «sin nota»: nunca cero;
 * los pesos se renormalizan sobre los criterios que sí tienen nota.
 */
class CalculoPromedios
{
    /**
     * @param  array<int|string, mixed>  $valores  literal: lista de letras; numérica: ['minimo' => n, 'maximo' => n]
     * @param  array<string, int|float>|null  $equivalentes  letra → número; null en escalas numéricas
     */
    public function __construct(
        private readonly array $valores,
        private readonly ?array $equivalentes,
        private readonly ?string $umbral,
    ) {}

    public static function deEscala(EscalaCalificacion $escala): self
    {
        return new self($escala->valores ?? [], $escala->equivalentes, $escala->umbral_aprobacion);
    }

    public function esLiteral(): bool
    {
        return $this->equivalentes !== null;
    }

    /**
     * Valida un valor escrito por el Docente y devuelve cómo se guarda.
     *
     * @return array{valor: string, numerico: float}
     */
    public function normalizar(string $crudo): array
    {
        $texto = trim($crudo);

        if ($this->esLiteral()) {
            $letra = $this->claveLiteral($texto)
                ?? throw new EvaluacionIncumplida('El valor no pertenece a la escala de calificación.');

            return ['valor' => $letra, 'numerico' => (float) $this->equivalentes[$letra]];
        }

        if (! preg_match('/^\d{1,3}(?:[.,]\d{1,2})?$/', $texto)) {
            throw new EvaluacionIncumplida('El valor no pertenece a la escala de calificación.');
        }

        $numero = (float) str_replace(',', '.', $texto);

        if ($numero < ($this->valores['minimo'] ?? 0) || $numero > ($this->valores['maximo'] ?? 20)) {
            throw new EvaluacionIncumplida('El valor no pertenece a la escala de calificación.');
        }

        return ['valor' => rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.'), 'numerico' => $numero];
    }

    /**
     * Promedio ponderado de los criterios con nota; `null` si ninguno la tiene.
     *
     * @param  iterable<array{peso: int|float|string, nota: int|float|string|null}>  $criterios
     */
    public function promedioPonderado(iterable $criterios): ?float
    {
        $suma = 0.0;
        $pesos = 0.0;

        foreach ($criterios as $criterio) {
            if ($criterio['nota'] === null || $criterio['nota'] === '') {
                continue;
            }

            $suma += (float) $criterio['peso'] * (float) $criterio['nota'];
            $pesos += (float) $criterio['peso'];
        }

        return $pesos > 0 ? round($suma / $pesos, 2) : null;
    }

    /**
     * Promedio simple de los promedios existentes (anual por curso, general del alumno); `null` si no hay.
     *
     * @param  iterable<float|int|null>  $promedios
     */
    public function promedioSimple(iterable $promedios): ?float
    {
        $existentes = [];

        foreach ($promedios as $promedio) {
            if ($promedio !== null) {
                $existentes[] = (float) $promedio;
            }
        }

        return $existentes === [] ? null : round(array_sum($existentes) / count($existentes), 2);
    }

    /** Letra de la escala literal más cercana al número (empate → la de mayor equivalente). */
    public function letraDe(float $numerico): string
    {
        $mejor = null;

        foreach ($this->equivalentes ?? [] as $letra => $equivalente) {
            $distancia = abs($equivalente - $numerico);

            if ($mejor === null || $distancia < $mejor['distancia'] || ($distancia === $mejor['distancia'] && $equivalente > $mejor['equivalente'])) {
                $mejor = ['letra' => (string) $letra, 'distancia' => $distancia, 'equivalente' => $equivalente];
            }
        }

        return $mejor['letra'] ?? '';
    }

    /** Calificativo para mostrar: la letra en escala literal, el número (2 decimales) en la numérica; `null` → «sin notas». */
    public function etiqueta(?float $numerico): ?string
    {
        if ($numerico === null) {
            return null;
        }

        return $this->esLiteral() ? $this->letraDe($numerico) : Formato::decimal($numerico, 2);
    }

    /** Aprobado según el umbral de la escala; en literal se compara la letra mostrada. `null` si no hay promedio ni umbral. */
    public function aprueba(?float $numerico): ?bool
    {
        if ($numerico === null || $this->umbral === null) {
            return null;
        }

        if ($this->esLiteral()) {
            $clave = $this->claveLiteral($this->umbral);
            $minimo = $clave === null ? null : $this->equivalentes[$clave];

            return $minimo === null ? null : $this->equivalentes[$this->letraDe($numerico)] >= $minimo;
        }

        return $numerico >= (float) str_replace(',', '.', $this->umbral);
    }

    /** Clave de la escala literal que coincide con el texto sin distinguir mayúsculas; `null` si no hay. */
    private function claveLiteral(string $texto): ?string
    {
        foreach (array_keys($this->equivalentes ?? []) as $clave) {
            if (mb_strtoupper((string) $clave) === mb_strtoupper(trim($texto))) {
                return (string) $clave;
            }
        }

        return null;
    }

    /** Los pesos suman exactamente 100,00 (se compara en centésimas) y cada uno está entre 0 (excluido) y 100. */
    public static function pesosValidos(array $pesos): bool
    {
        if ($pesos === []) {
            return false;
        }

        $centesimas = 0;

        foreach ($pesos as $peso) {
            $c = (int) round((float) $peso * 100);

            if ($c <= 0 || $c > 10000) {
                return false;
            }

            $centesimas += $c;
        }

        return $centesimas === 10000;
    }
}
