<?php

namespace App\Domain\Championship;

class TeamPointsCalculator
{
    /**
     * Variação de pontos de um time em uma partida:
     * +1 por gol marcado e -1 por gol sofrido.
     */
    public function calculate(int $goalsScored, int $goalsConceded): int
    {
        return (new Score($goalsScored))->value - (new Score($goalsConceded))->value;
    }
}
