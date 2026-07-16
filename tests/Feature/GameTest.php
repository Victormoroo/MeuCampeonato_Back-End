<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Models\Championship;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_relationships_resolve_correctly(): void
    {
        $championship = Championship::factory()->create();

        $home = Team::factory()->for($championship)->create([
            'name' => 'Home',
            'registration_order' => 1,
        ]);
        $away = Team::factory()->for($championship)->create([
            'name' => 'Away',
            'registration_order' => 2,
        ]);

        $game = Game::create([
            'championship_id' => $championship->id,
            'stage' => GameStage::Semifinal,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 3,
            'away_score' => 1,
            'winner_team_id' => $home->id,
            'loser_team_id' => $away->id,
        ]);

        $game->refresh();

        $this->assertTrue($game->championship->is($championship));
        $this->assertTrue($game->homeTeam->is($home));
        $this->assertTrue($game->awayTeam->is($away));
        $this->assertTrue($game->winner->is($home));
        $this->assertTrue($game->loser->is($away));
    }

    public function test_stage_is_cast_to_game_stage_enum(): void
    {
        $championship = Championship::factory()->create();

        $home = Team::factory()->for($championship)->create([
            'name' => 'Home',
            'registration_order' => 1,
        ]);
        $away = Team::factory()->for($championship)->create([
            'name' => 'Away',
            'registration_order' => 2,
        ]);

        $game = Game::create([
            'championship_id' => $championship->id,
            'stage' => GameStage::ThirdPlace,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $this->assertInstanceOf(GameStage::class, $game->fresh()->stage);
        $this->assertSame(GameStage::ThirdPlace, $game->fresh()->stage);
        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'stage' => 'third_place',
        ]);
    }

    public function test_deleting_a_team_referenced_by_a_game_is_blocked_to_preserve_history(): void
    {
        $championship = Championship::factory()->create();

        $home = Team::factory()->for($championship)->create([
            'name' => 'Home',
            'registration_order' => 1,
        ]);
        $away = Team::factory()->for($championship)->create([
            'name' => 'Away',
            'registration_order' => 2,
        ]);

        Game::create([
            'championship_id' => $championship->id,
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        // A foreign key RESTRICT impede apagar um time isolado que ainda
        // participa de um jogo, preservando o histórico da partida.
        $this->expectException(QueryException::class);

        $home->delete();
    }
}
