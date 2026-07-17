<?php

namespace App\Contracts;

use App\Domain\Championship\GameScore;

interface ScoreGenerator
{
    public function generate(): GameScore;
}
