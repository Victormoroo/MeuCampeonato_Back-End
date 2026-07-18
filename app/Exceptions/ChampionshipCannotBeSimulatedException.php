<?php

namespace App\Exceptions;

use App\Models\Championship;
use RuntimeException;

class ChampionshipCannotBeSimulatedException extends RuntimeException
{
    public static function notPending(Championship $championship): self
    {
        return new self("O campeonato {$championship->id} não está pendente e não pode ser simulado.");
    }

    public static function wrongTeamCount(int $count): self
    {
        return new self("A simulação exige exatamente oito times; o campeonato possui {$count}.");
    }

    public static function alreadyHasGames(Championship $championship): self
    {
        return new self("O campeonato {$championship->id} já possui partidas e não pode ser simulado novamente.");
    }
}
