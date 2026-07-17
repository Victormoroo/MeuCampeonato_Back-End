<?php

namespace App\Domain\Championship;

use App\Models\Team;

final readonly class GameOutcome
{
    public function __construct(
        public Team $winner,
        public Team $loser,
    ) {}
}
