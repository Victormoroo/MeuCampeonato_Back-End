<?php

namespace App\Exceptions;

use App\Models\Game;
use RuntimeException;

class InvalidGameParticipantsException extends RuntimeException
{
    public static function sameTeam(Game $game): self
    {
        return new self("A partida {$game->id} não pode ter o mesmo time nos dois lados.");
    }

    public static function missingParticipant(Game $game): self
    {
        return new self("A partida {$game->id} possui um participante que não pôde ser recuperado.");
    }

    public static function foreignParticipant(Game $game): self
    {
        return new self("A partida {$game->id} possui um time que não pertence ao seu campeonato.");
    }
}
