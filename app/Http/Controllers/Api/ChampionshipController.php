<?php

namespace App\Http\Controllers\Api;

use App\Actions\Championships\CreateChampionship;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChampionshipRequest;
use App\Http\Resources\ChampionshipResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChampionshipController extends Controller
{
    public function store(StoreChampionshipRequest $request, CreateChampionship $action): JsonResponse
    {
        $championship = $action->handle($request->validated());

        return ChampionshipResource::make($championship)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
