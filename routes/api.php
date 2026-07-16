<?php

use App\Http\Controllers\Api\ChampionshipController;
use Illuminate\Support\Facades\Route;

Route::post('/championships', [ChampionshipController::class, 'store']);
