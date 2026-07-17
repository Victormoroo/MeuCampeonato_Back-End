<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'sequence' => $this->sequence,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'played_at' => $this->played_at?->toIso8601String(),
            // whenLoaded com closure (2 args): omite o campo se o relacionamento
            // não foi carregado; retorna null se foi carregado mas a FK é null
            // (partida sem resultado); caso contrário, o TeamResource.
            'home_team' => $this->whenLoaded('homeTeam', fn () => TeamResource::make($this->homeTeam)),
            'away_team' => $this->whenLoaded('awayTeam', fn () => TeamResource::make($this->awayTeam)),
            'winner' => $this->whenLoaded('winner', fn () => TeamResource::make($this->winner)),
            'loser' => $this->whenLoaded('loser', fn () => TeamResource::make($this->loser)),
        ];
    }
}
