<?php

namespace App\Enums;

enum GameStage: string
{
    case Quarterfinal = 'quarterfinal';
    case Semifinal = 'semifinal';
    case ThirdPlace = 'third_place';
    case Final = 'final';
}
