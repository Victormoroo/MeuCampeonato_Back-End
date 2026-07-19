<?php

use App\Http\Controllers\Api\ChampionshipController;
use App\Http\Controllers\Api\SimulateChampionshipController;
use Illuminate\Support\Facades\Route;

Route::apiResource('championships', ChampionshipController::class)
    ->only(['index', 'store', 'show']);

Route::post('championships/{championship}/simulate', SimulateChampionshipController::class)
    ->name('championships.simulate');
