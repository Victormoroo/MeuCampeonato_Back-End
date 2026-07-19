<?php

namespace Tests\Feature\Api;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Exceptions\ScoreGenerationException;
use App\Models\Championship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SimulateChampionshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_simulation_returns_200_with_the_completed_championship(): void
    {
        $championship = $this->makeChampionship();
        $this->fakeGenerator($this->homeWinScores());

        $response = $this->postJson($this->endpoint($championship));

        $response->assertOk();
        $response->assertJsonPath('data.id', $championship->id);
        $response->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($response->json('data.started_at'));
        $this->assertNotNull($response->json('data.completed_at'));

        $response->assertJsonCount(8, 'data.teams');
        $response->assertJsonCount(8, 'data.games');

        $games = collect($response->json('data.games'));
        $this->assertSame(4, $games->where('stage', 'quarterfinal')->count());
        $this->assertSame(2, $games->where('stage', 'semifinal')->count());
        $this->assertSame(1, $games->where('stage', 'third_place')->count());
        $this->assertSame(1, $games->where('stage', 'final')->count());

        foreach ($response->json('data.games') as $game) {
            $this->assertNotNull($game['home_score']);
            $this->assertNotNull($game['away_score']);
            $this->assertNotNull($game['played_at']);
            $this->assertNotNull($game['home_team']);
            $this->assertNotNull($game['away_team']);
            $this->assertNotNull($game['winner']);
            $this->assertNotNull($game['loser']);
        }

        $positions = collect($response->json('data.teams'))
            ->pluck('final_position')
            ->filter(fn (?int $position) => $position !== null)
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1, 2, 3, 4], $positions);

        $this->assertDatabaseCount('championships', 1);
        $this->assertDatabaseCount('teams', 8);
        $this->assertDatabaseCount('games', 8);
        $this->assertDatabaseHas('championships', [
            'id' => $championship->id,
            'status' => 'completed',
        ]);
    }

    public function test_teams_are_ordered_by_registration_order_and_games_by_id(): void
    {
        $championship = $this->makeChampionship();
        $this->fakeGenerator($this->homeWinScores());

        $response = $this->postJson($this->endpoint($championship));

        $response->assertOk();
        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8],
            collect($response->json('data.teams'))->pluck('registration_order')->all(),
        );

        $gameIds = collect($response->json('data.games'))->pluck('id')->all();
        $this->assertSame(collect($gameIds)->sort()->values()->all(), $gameIds);
    }

    public function test_the_generator_is_called_exactly_eight_times(): void
    {
        $championship = $this->makeChampionship();
        $generator = $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))->assertOk();

        $this->assertSame(8, $generator->calls);
    }

    public function test_a_second_simulation_returns_409_without_side_effects(): void
    {
        $championship = $this->makeChampionship();
        $generator = $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))->assertOk();
        $this->assertSame(8, $generator->calls);

        $second = $this->postJson($this->endpoint($championship));

        $second->assertStatus(409);
        $second->assertJsonStructure(['message']);
        $this->assertSame(8, $generator->calls);
        $this->assertDatabaseCount('games', 8);
    }

    public function test_an_in_progress_championship_returns_409(): void
    {
        $championship = $this->makeChampionship(8, ChampionshipStatus::InProgress);
        $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_a_completed_championship_returns_409(): void
    {
        $championship = $this->makeChampionship(8, ChampionshipStatus::Completed);
        $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_a_championship_with_wrong_team_count_returns_409(): void
    {
        $championship = $this->makeChampionship(7);
        $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_a_pending_championship_with_existing_games_returns_409(): void
    {
        $championship = $this->makeChampionship();
        $teams = $championship->teams()->orderBy('id')->take(2)->get();
        $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
        ]);

        $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_a_preliminary_rejection_does_not_call_the_generator_or_persist(): void
    {
        $championship = $this->makeChampionship(7);
        $generator = $this->fakeGenerator($this->homeWinScores());

        $this->postJson($this->endpoint($championship))->assertStatus(409);

        $this->assertSame(0, $generator->calls);
        $this->assertDatabaseCount('games', 0);
        $this->assertSame(ChampionshipStatus::Pending, $championship->refresh()->status);
    }

    public function test_a_generator_failure_returns_502_and_persists_nothing(): void
    {
        $championship = $this->makeChampionship();
        // Falha na 5ª geração (índice 4), depois de já gerar quatro placares.
        $this->fakeGenerator($this->homeWinScores(), failAt: 4);

        $response = $this->postJson($this->endpoint($championship));

        $response->assertStatus(502);
        $response->assertJsonStructure(['message']);

        $championship->refresh();
        $this->assertSame(ChampionshipStatus::Pending, $championship->status);
        $this->assertNull($championship->started_at);
        $this->assertNull($championship->completed_at);
        $this->assertDatabaseCount('games', 0);

        foreach ($championship->teams as $team) {
            $this->assertSame(0, $team->points);
            $this->assertNull($team->final_position);
        }
    }

    public function test_a_missing_championship_returns_404_json(): void
    {
        $response = $this->postJson('/api/championships/999999/simulate');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    public function test_error_responses_are_json_without_an_accept_header(): void
    {
        $championship = $this->makeChampionship(8, ChampionshipStatus::Completed);
        $this->fakeGenerator($this->homeWinScores());

        // POST comum, SEM cabeçalho Accept: application/json.
        $response = $this->post($this->endpoint($championship));

        $response->assertStatus(409);
        $response->assertJsonStructure(['message']);
    }

    // ---------------------------------------------------------------- Helpers

    private function endpoint(Championship $championship): string
    {
        return "/api/championships/{$championship->id}/simulate";
    }

    private function makeChampionship(int $teams = 8, ChampionshipStatus $status = ChampionshipStatus::Pending): Championship
    {
        $championship = Championship::factory()->create(['status' => $status]);

        for ($order = 1; $order <= $teams; $order++) {
            $championship->teams()->create([
                'name' => "Team {$order}",
                'registration_order' => $order,
                'points' => 0,
            ]);
        }

        return $championship;
    }

    /**
     * @return array<int, GameScore>
     */
    private function homeWinScores(): array
    {
        return [
            new GameScore(1, 0),
            new GameScore(2, 0),
            new GameScore(3, 0),
            new GameScore(4, 0),
            new GameScore(5, 0),
            new GameScore(6, 0),
            new GameScore(7, 0),
            new GameScore(7, 1),
        ];
    }

    /**
     * Substitui o ScoreGenerator do container por uma fake determinística em
     * memória e devolve a instância (para inspecionar o contador de chamadas).
     *
     * @param  array<int, GameScore>  $scores
     */
    private function fakeGenerator(array $scores, ?int $failAt = null): ScoreGenerator
    {
        $fake = new class($scores, $failAt) implements ScoreGenerator
        {
            public int $calls = 0;

            /** @var array<int, GameScore> */
            private array $scores;

            public function __construct(array $scores, private readonly ?int $failAt)
            {
                $this->scores = array_values($scores);
            }

            public function generate(): GameScore
            {
                $index = $this->calls;
                $this->calls++;

                if ($this->failAt !== null && $index === $this->failAt) {
                    throw ScoreGenerationException::processFailed();
                }

                if (! array_key_exists($index, $this->scores)) {
                    throw new LogicException('ScoreGenerator chamado mais vezes que o configurado.');
                }

                return $this->scores[$index];
            }
        };

        $this->app->instance(ScoreGenerator::class, $fake);

        return $fake;
    }
}
