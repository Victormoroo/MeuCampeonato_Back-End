<?php

namespace Tests\Feature\Api;

use App\Enums\GameStage;
use App\Models\Championship;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowChampionshipTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/championships';

    public function test_it_responds_200_with_the_championship_fields(): void
    {
        $championship = Championship::factory()->create();

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'name', 'status', 'started_at', 'completed_at', 'created_at', 'updated_at', 'teams', 'games',
            ],
        ]);
        $response->assertJsonPath('data.id', $championship->id);
        $response->assertJsonPath('data.name', $championship->name);
        $response->assertJsonPath('data.status', $championship->status->value);
    }

    public function test_it_returns_empty_arrays_for_a_championship_without_teams_or_games(): void
    {
        $championship = Championship::factory()->create();

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();
        $response->assertJsonCount(0, 'data.teams');
        $response->assertJsonCount(0, 'data.games');
    }

    public function test_it_returns_teams_ordered_by_registration_order(): void
    {
        $championship = Championship::factory()->create();
        foreach ([5, 2, 8, 1, 7, 3, 6, 4] as $order) {
            Team::factory()->for($championship)->create([
                'name' => 'Team '.$order,
                'registration_order' => $order,
            ]);
        }

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();
        $orders = collect($response->json('data.teams'))->pluck('registration_order')->all();
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $orders);
    }

    public function test_it_returns_games_in_ascending_id_order_with_team_relationships(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->twoTeams($championship);

        $first = $championship->games()->create([
            'stage' => GameStage::Quarterfinal, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
        ]);
        $second = $championship->games()->create([
            'stage' => GameStage::Semifinal, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
        ]);

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();
        $ids = collect($response->json('data.games'))->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], $ids);

        $response->assertJsonPath('data.games.0.stage', 'quarterfinal');
        $response->assertJsonPath('data.games.0.home_team.id', $home->id);
        $response->assertJsonPath('data.games.0.away_team.id', $away->id);
    }

    public function test_it_returns_winner_and_loser_when_the_game_was_played(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->twoTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Final, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'home_score' => 2, 'away_score' => 1,
            'winner_team_id' => $home->id, 'loser_team_id' => $away->id,
            'played_at' => now(),
        ]);

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();
        $response->assertJsonPath('data.games.0.winner.id', $home->id);
        $response->assertJsonPath('data.games.0.loser.id', $away->id);
        $response->assertJsonPath('data.games.0.home_score', 2);
        $response->assertJsonPath('data.games.0.away_score', 1);
    }

    public function test_it_returns_winner_and_loser_as_null_when_the_game_has_no_result(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->twoTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Quarterfinal, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
        ]);

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $response->assertOk();

        $game = $response->json('data.games.0');
        // Presentes (relacionamento carregado) porém null (partida sem resultado).
        $this->assertArrayHasKey('winner', $game);
        $this->assertArrayHasKey('loser', $game);
        $this->assertNull($game['winner']);
        $this->assertNull($game['loser']);
        $this->assertNull($game['home_score']);
        $this->assertNull($game['away_score']);
        $this->assertNull($game['played_at']);
    }

    public function test_the_game_resource_does_not_expose_internal_foreign_keys(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->twoTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Final, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
        ]);

        $response = $this->getJson(self::ENDPOINT.'/'.$championship->id);

        $game = $response->json('data.games.0');
        foreach (['championship_id', 'home_team_id', 'away_team_id', 'winner_team_id', 'loser_team_id'] as $key) {
            $this->assertArrayNotHasKey($key, $game);
        }
    }

    public function test_it_returns_404_json_for_a_missing_id(): void
    {
        $response = $this->getJson(self::ENDPOINT.'/999999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    public function test_a_get_request_does_not_change_the_database(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->twoTeams($championship);
        $championship->games()->create([
            'stage' => GameStage::Final, 'sequence' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
        ]);

        $before = [Championship::count(), Team::count(), Game::count()];

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson(self::ENDPOINT.'/'.$championship->id)->assertOk();

        $after = [Championship::count(), Team::count(), Game::count()];
        $this->assertSame($before, $after);
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function twoTeams(Championship $championship): array
    {
        $home = Team::factory()->for($championship)->create(['name' => 'Home', 'registration_order' => 1]);
        $away = Team::factory()->for($championship)->create(['name' => 'Away', 'registration_order' => 2]);

        return [$home, $away];
    }
}
