<?php

namespace Tests\Feature\Api;

use App\Enums\GameStage;
use App\Models\Championship;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteChampionshipTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(Championship $championship): string
    {
        return "/api/championships/{$championship->id}";
    }

    public function test_it_deletes_a_championship_and_returns_204(): void
    {
        $championship = Championship::factory()->create();

        $response = $this->deleteJson($this->endpoint($championship));

        $response->assertNoContent(); // HTTP 204
        $this->assertDatabaseMissing('championships', ['id' => $championship->id]);
    }

    public function test_it_cascades_and_removes_teams_and_games(): void
    {
        $championship = Championship::factory()->create();
        $home = Team::factory()->for($championship)->create(['name' => 'Home', 'registration_order' => 1]);
        $away = Team::factory()->for($championship)->create(['name' => 'Away', 'registration_order' => 2]);

        // Partida já disputada (winner/loser preenchidos) para provar que o
        // cascade remove os jogos mesmo com todas as FKs de time populadas.
        $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 2,
            'away_score' => 1,
            'winner_team_id' => $home->id,
            'loser_team_id' => $away->id,
            'played_at' => now(),
        ]);

        $this->deleteJson($this->endpoint($championship))->assertNoContent();

        $this->assertDatabaseCount('championships', 0);
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('games', 0);
    }

    public function test_it_returns_404_json_for_a_missing_championship(): void
    {
        $response = $this->deleteJson('/api/championships/999999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    public function test_it_only_deletes_the_target_championship(): void
    {
        $target = Championship::factory()->create();
        $other = Championship::factory()->create();
        Team::factory()->for($other)->create(['registration_order' => 1]);

        $this->deleteJson($this->endpoint($target))->assertNoContent();

        $this->assertDatabaseMissing('championships', ['id' => $target->id]);
        $this->assertDatabaseHas('championships', ['id' => $other->id]);
        $this->assertDatabaseCount('teams', 1); // o time do outro campeonato permanece
    }
}
