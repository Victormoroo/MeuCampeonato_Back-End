<?php

namespace App\Domain\Championship;

use InvalidArgumentException;

/**
 * Quantidade de gols de um lado de uma partida. Nunca negativa.
 *
 * Não impõe limite superior: o teto de gols é responsabilidade do futuro
 * gerador de placar, não desta camada de cálculo.
 */
final readonly class Score
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('O número de gols não pode ser negativo.');
        }
    }
}
