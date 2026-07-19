<?php

namespace App\Http\Controllers\Api;

use App\Actions\Championships\SimulateChampionship;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipResource;
use App\Models\Championship;

class SimulateChampionshipController extends Controller
{
    public function __invoke(Championship $championship, SimulateChampionship $action): ChampionshipResource
    {
        return ChampionshipResource::make($action->handle($championship));
    }
}
