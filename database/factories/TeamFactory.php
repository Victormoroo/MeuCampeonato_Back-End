<?php

namespace Database\Factories;

use App\Models\Championship;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * Gera nome e registration_order. Não cria oito times automaticamente:
     * quando um teste precisar de vários times, deve definir explicitamente a
     * registration_order (e o nome) via state/Sequence.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'championship_id' => Championship::factory(),
            'name' => fake()->company(),
            'registration_order' => fake()->numberBetween(1, 8),
            'points' => 0,
            'final_position' => null,
        ];
    }
}
