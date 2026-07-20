<?php

namespace App\Http\Resources;

use App\Models\Championship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Championship
 */
class ChampionshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'teams_count' => $this->whenCounted('teams'),
            'games_count' => $this->whenCounted('games'),
            // Nome do time campeão (final_position = 1); null se ainda não houver.
            'champion' => $this->whenLoaded('champion', fn () => $this->champion->name),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'games' => GameResource::collection($this->whenLoaded('games')),
        ];
    }
}
