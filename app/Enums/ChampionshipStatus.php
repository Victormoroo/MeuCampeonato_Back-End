<?php

namespace App\Enums;

enum ChampionshipStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
