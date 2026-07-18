<?php

namespace App\Exceptions;

use App\Models\Game;
use RuntimeException;

class GameAlreadyPlayedException extends RuntimeException
{
    public static function forGame(Game $game): self
    {
        return new self("A partida {$game->id} já foi disputada.");
    }
}
