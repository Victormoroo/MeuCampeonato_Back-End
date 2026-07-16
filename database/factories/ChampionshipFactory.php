<?php

namespace Database\Factories;

use App\Enums\ChampionshipStatus;
use App\Models\Championship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Championship>
 */
class ChampionshipFactory extends Factory
{
    protected $model = Championship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            // Um campeonato nasce pendente por padrão.
            'status' => ChampionshipStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
