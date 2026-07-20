<?php

namespace App\Http\Controllers\Api;

use App\Actions\Championships\CreateChampionship;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChampionshipRequest;
use App\Http\Resources\ChampionshipResource;
use App\Models\Championship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ChampionshipController extends Controller
{
    /**
     * Lista paginada de campeonatos (do mais recente para o mais antigo),
     * carregando apenas as contagens de times e jogos.
     */
    public function index(): AnonymousResourceCollection
    {
        $championships = Championship::query()
            ->withCount(['teams', 'games'])
            ->orderByDesc('id')
            ->paginate(15);

        return ChampionshipResource::collection($championships);
    }

    public function store(StoreChampionshipRequest $request, CreateChampionship $action): JsonResponse
    {
        $championship = $action->handle($request->validated());

        return ChampionshipResource::make($championship)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Detalhe de um campeonato: times por ordem de inscrição e jogos por id,
     * com os times de cada jogo carregados (eager loading, sem N+1).
     */
    public function show(Championship $championship): ChampionshipResource
    {
        $championship->load([
            'teams' => fn ($query) => $query->orderBy('registration_order'),
            'games' => fn ($query) => $query->orderBy('id'),
            'games.homeTeam',
            'games.awayTeam',
            'games.winner',
            'games.loser',
        ]);

        return ChampionshipResource::make($championship);
    }

    /**
     * Remove um campeonato. As foreign keys ON DELETE CASCADE de championship_id
     * removem automaticamente os times e jogos associados.
     */
    public function destroy(Championship $championship): Response
    {
        $championship->delete();

        return response()->noContent();
    }
}
