<?php

namespace App\Domain\Championship;

use App\Models\Team;

class GameWinnerResolver
{
    /**
     * Determina o vencedor de uma partida, nesta ordem de critérios:
     * 1) maior placar; 2) maior pontuação acumulada; 3) menor ordem de inscrição.
     */
    public function resolve(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): GameOutcome
    {
        $home = new Score($homeScore);
        $away = new Score($awayScore);

        if ($home->value !== $away->value) {
            return $home->value > $away->value
                ? new GameOutcome($homeTeam, $awayTeam)
                : new GameOutcome($awayTeam, $homeTeam);
        }

        if ($homeTeam->points !== $awayTeam->points) {
            return $homeTeam->points > $awayTeam->points
                ? new GameOutcome($homeTeam, $awayTeam)
                : new GameOutcome($awayTeam, $homeTeam);
        }

        return $homeTeam->registration_order < $awayTeam->registration_order
            ? new GameOutcome($homeTeam, $awayTeam)
            : new GameOutcome($awayTeam, $homeTeam);
    }
}
