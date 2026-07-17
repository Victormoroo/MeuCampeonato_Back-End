<?php

namespace App\Domain\Championship;

final readonly class GameScore
{
    public function __construct(
        public int $homeScore,
        public int $awayScore,
    ) {}
}
