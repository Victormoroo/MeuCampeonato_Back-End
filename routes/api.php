<?php

use App\Http\Controllers\Api\ChampionshipController;
use Illuminate\Support\Facades\Route;

Route::apiResource('championships', ChampionshipController::class)
    ->only(['index', 'store', 'show']);
