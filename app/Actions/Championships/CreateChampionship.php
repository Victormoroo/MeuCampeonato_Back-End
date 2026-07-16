<?php

namespace App\Actions\Championships;

use App\Enums\ChampionshipStatus;
use App\Models\Championship;
use Illuminate\Support\Facades\DB;

class CreateChampionship
{
    /**
     * Cria um campeonato pendente com exatamente oito times, na ordem de
     * inscrição recebida. Recebe dados já validados e normalizados.
     *
     * @param  array{name: string, teams: array<int, string>}  $data
     */
    public function handle(array $data): Championship
    {
        return DB::transaction(function () use ($data): Championship {
            $championship = Championship::create([
                'name' => $data['name'],
                'status' => ChampionshipStatus::Pending,
                'started_at' => null,
                'completed_at' => null,
            ]);

            foreach (array_values($data['teams']) as $index => $teamName) {
                $championship->teams()->create([
                    'name' => $teamName,
                    'registration_order' => $index + 1,
                    'points' => 0,
                    'final_position' => null,
                ]);
            }

            // Carrega os times já ordenados pela ordem de inscrição.
            $championship->load([
                'teams' => fn ($query) => $query->orderBy('registration_order'),
            ]);

            return $championship;
        });
    }
}
