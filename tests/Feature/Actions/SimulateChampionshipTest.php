<?php

namespace Tests\Feature\Actions;

use App\Actions\Championships\PlayGame;
use App\Actions\Championships\SimulateChampionship;
use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Domain\Championship\GameWinnerResolver;
use App\Domain\Championship\TeamPointsCalculator;
use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Exceptions\ChampionshipCannotBeSimulatedException;
use App\Exceptions\ScoreGenerationException;
use App\Models\Championship;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class SimulateChampionshipTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Happy path

    public function test_it_completes_a_championship_with_eight_teams(): void
    {
        $championship = $this->makeChampionship();

        $result = $this->action($this->generator($this->homeWinScores()))->handle($championship);

        $this->assertSame(ChampionshipStatus::Completed, $result->status);
        $this->assertNotNull($result->started_at);
        $this->assertNotNull($result->completed_at);

        $this->assertDatabaseCount('games', 8);
        $this->assertSame(4, $this->stageGames($result, GameStage::Quarterfinal)->count());
        $this->assertSame(2, $this->stageGames($result, GameStage::Semifinal)->count());
        $this->assertSame(1, $this->stageGames($result, GameStage::ThirdPlace)->count());
        $this->assertSame(1, $this->stageGames($result, GameStage::Final)->count());
    }

    public function test_the_sequences_are_correct_in_each_phase(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $this->assertSame(
            [1, 2, 3, 4],
            $this->stageGames($result, GameStage::Quarterfinal)->sortBy('sequence')->pluck('sequence')->all(),
        );
        $this->assertSame(
            [1, 2],
            $this->stageGames($result, GameStage::Semifinal)->sortBy('sequence')->pluck('sequence')->all(),
        );
        $this->assertSame(1, $this->stageGames($result, GameStage::ThirdPlace)->first()->sequence);
        $this->assertSame(1, $this->stageGames($result, GameStage::Final)->first()->sequence);
    }

    public function test_all_eight_teams_play_once_in_the_quarterfinals_without_self_matches(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $participants = $this->stageGames($result, GameStage::Quarterfinal)
            ->flatMap(fn (Game $game) => [$game->home_team_id, $game->away_team_id]);

        $this->assertCount(8, $participants);
        $this->assertCount(8, $participants->unique());
        $this->assertEqualsCanonicalizing($result->teams->pluck('id')->all(), $participants->all());

        foreach ($result->games as $game) {
            $this->assertNotSame($game->home_team_id, $game->away_team_id);
        }
    }

    public function test_the_bracket_progression_is_consistent(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $quarters = $this->stageGames($result, GameStage::Quarterfinal);
        $semis = $this->stageGames($result, GameStage::Semifinal);
        $third = $this->stageGames($result, GameStage::ThirdPlace)->first();
        $final = $this->stageGames($result, GameStage::Final)->first();

        $quarterWinners = $quarters->pluck('winner_team_id');
        $semiParticipants = $semis->flatMap(fn (Game $g) => [$g->home_team_id, $g->away_team_id]);

        // Semifinalistas são exatamente os vencedores das quartas, cada um uma vez.
        $this->assertEqualsCanonicalizing($quarterWinners->all(), $semiParticipants->all());
        $this->assertCount(4, $semiParticipants->unique());

        // Terceiro lugar = perdedores das semifinais.
        $this->assertEqualsCanonicalizing(
            $semis->pluck('loser_team_id')->all(),
            [$third->home_team_id, $third->away_team_id],
        );

        // Final = vencedores das semifinais.
        $this->assertEqualsCanonicalizing(
            $semis->pluck('winner_team_id')->all(),
            [$final->home_team_id, $final->away_team_id],
        );

        // Terceiro lugar é criado antes da final.
        $this->assertLessThan($final->id, $third->id);
    }

    public function test_every_game_is_fully_played(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        foreach ($result->games as $game) {
            $this->assertNotNull($game->home_score);
            $this->assertNotNull($game->away_score);
            $this->assertNotNull($game->winner_team_id);
            $this->assertNotNull($game->loser_team_id);
            $this->assertNotNull($game->played_at);
        }
    }

    public function test_the_generator_is_called_exactly_eight_times(): void
    {
        $generator = $this->generator($this->homeWinScores());

        $this->action($generator)->handle($this->makeChampionship());

        $this->assertSame(8, $generator->calls);
    }

    public function test_scores_are_consumed_in_phase_order(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $actual = $result->games
            ->map(fn (Game $game) => [$game->home_score, $game->away_score])
            ->all();

        $expected = array_map(
            fn (GameScore $score) => [$score->homeScore, $score->awayScore],
            $this->homeWinScores(),
        );

        $this->assertSame($expected, $actual);
    }

    public function test_final_points_match_the_goal_difference_across_all_games(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $expected = [];
        foreach ($result->games as $game) {
            $expected[$game->home_team_id] = ($expected[$game->home_team_id] ?? 0) + ($game->home_score - $game->away_score);
            $expected[$game->away_team_id] = ($expected[$game->away_team_id] ?? 0) + ($game->away_score - $game->home_score);
        }

        foreach ($result->teams as $team) {
            $this->assertSame($expected[$team->id] ?? 0, $team->points);
        }
    }

    public function test_final_positions_are_assigned(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $third = $this->stageGames($result, GameStage::ThirdPlace)->first();
        $final = $this->stageGames($result, GameStage::Final)->first();
        $teamsById = $result->teams->keyBy('id');

        $this->assertSame(1, $teamsById[$final->winner_team_id]->final_position);
        $this->assertSame(2, $teamsById[$final->loser_team_id]->final_position);
        $this->assertSame(3, $teamsById[$third->winner_team_id]->final_position);
        $this->assertSame(4, $teamsById[$third->loser_team_id]->final_position);

        foreach ($this->stageGames($result, GameStage::Quarterfinal)->pluck('loser_team_id') as $loserId) {
            $this->assertNull($teamsById[$loserId]->final_position);
        }

        // Exatamente um time por posição de 1 a 4.
        $positions = $result->teams
            ->pluck('final_position')
            ->filter(fn (?int $position) => $position !== null)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([1, 2, 3, 4], $positions);
    }

    public function test_it_returns_loaded_and_ordered_relationships(): void
    {
        $result = $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship());

        $this->assertTrue($result->relationLoaded('teams'));
        $this->assertTrue($result->relationLoaded('games'));

        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8],
            $result->teams->pluck('registration_order')->all(),
        );

        $gameIds = $result->games->pluck('id')->all();
        $this->assertSame(collect($gameIds)->sort()->values()->all(), $gameIds);

        $firstGame = $result->games->first();
        $this->assertTrue($firstGame->relationLoaded('homeTeam'));
        $this->assertTrue($firstGame->relationLoaded('awayTeam'));
        $this->assertTrue($firstGame->relationLoaded('winner'));
        $this->assertTrue($firstGame->relationLoaded('loser'));
    }

    public function test_handle_with_score_does_not_call_the_generator_again(): void
    {
        $simulationGenerator = $this->generator($this->homeWinScores());
        $playGameGenerator = $this->generator([]); // vazio: lança se for chamado

        $this->action($simulationGenerator, $playGameGenerator)->handle($this->makeChampionship());

        $this->assertSame(0, $playGameGenerator->calls);
        $this->assertSame(8, $simulationGenerator->calls);
    }

    // ---------------------------------------------------------------- Rejections

    public function test_a_championship_with_seven_teams_is_rejected(): void
    {
        $this->expectException(ChampionshipCannotBeSimulatedException::class);

        $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship(7));
    }

    public function test_a_championship_with_nine_teams_is_rejected(): void
    {
        $this->expectException(ChampionshipCannotBeSimulatedException::class);

        $this->action($this->generator($this->homeWinScores()))->handle($this->makeChampionship(9));
    }

    public function test_an_in_progress_championship_is_rejected(): void
    {
        $championship = $this->makeChampionship(8, ChampionshipStatus::InProgress);

        $this->expectException(ChampionshipCannotBeSimulatedException::class);

        $this->action($this->generator($this->homeWinScores()))->handle($championship);
    }

    public function test_a_completed_championship_is_rejected(): void
    {
        $championship = $this->makeChampionship(8, ChampionshipStatus::Completed);

        $this->expectException(ChampionshipCannotBeSimulatedException::class);

        $this->action($this->generator($this->homeWinScores()))->handle($championship);
    }

    public function test_a_championship_with_existing_games_is_rejected(): void
    {
        $championship = $this->makeChampionship();
        $teams = $championship->teams()->orderBy('id')->take(2)->get();
        $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
        ]);

        $this->expectException(ChampionshipCannotBeSimulatedException::class);

        $this->action($this->generator($this->homeWinScores()))->handle($championship);
    }

    public function test_a_preliminary_rejection_does_not_call_the_generator(): void
    {
        $generator = $this->generator($this->homeWinScores());

        try {
            $this->action($generator)->handle($this->makeChampionship(7));
            $this->fail('Expected ChampionshipCannotBeSimulatedException.');
        } catch (ChampionshipCannotBeSimulatedException) {
            // esperado
        }

        $this->assertSame(0, $generator->calls);
        $this->assertDatabaseCount('games', 0);
    }

    // ---------------------------------------------------------------- Failures

    public function test_a_generator_failure_changes_nothing(): void
    {
        $championship = $this->makeChampionship();
        // Falha na 5ª geração (índice 4), depois de já gerar quatro placares.
        $generator = $this->generator($this->homeWinScores(), failAt: 4);

        try {
            $this->action($generator)->handle($championship);
            $this->fail('Expected ScoreGenerationException.');
        } catch (ScoreGenerationException) {
            // esperado
        }

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

    public function test_a_persistence_failure_rolls_back_everything(): void
    {
        $championship = $this->makeChampionship();

        // Quatro quartas válidas, depois um placar negativo na primeira semifinal:
        // TeamPointsCalculator lançará InvalidArgumentException dentro da transação
        // geral, após partidas anteriores já terem sido persistidas.
        $scores = [
            new GameScore(1, 0),
            new GameScore(1, 0),
            new GameScore(1, 0),
            new GameScore(1, 0),
            new GameScore(-1, 0),
            new GameScore(1, 0),
            new GameScore(1, 0),
            new GameScore(1, 0),
        ];

        try {
            $this->action($this->generator($scores))->handle($championship);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado
        }

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

    public function test_two_stale_instances_do_not_double_simulate(): void
    {
        $championship = $this->makeChampionship();
        $first = Championship::findOrFail($championship->id);
        $second = Championship::findOrFail($championship->id);

        $this->action($this->generator($this->homeWinScores()))->handle($first);

        $secondGenerator = $this->generator($this->homeWinScores());

        try {
            $this->action($secondGenerator)->handle($second);
            $this->fail('Expected the second attempt to be rejected.');
        } catch (ChampionshipCannotBeSimulatedException) {
            // esperado
        }

        // Rejeitada na validação preliminar, antes de gerar qualquer placar.
        $this->assertSame(0, $secondGenerator->calls);

        $this->assertSame(8, Game::count());
        $this->assertSame(ChampionshipStatus::Completed, $championship->refresh()->status);
    }

    // ---------------------------------------------------------------- Helpers

    private function action(ScoreGenerator $generator, ?ScoreGenerator $playGameGenerator = null): SimulateChampionship
    {
        return new SimulateChampionship(
            $generator,
            new PlayGame(
                $playGameGenerator ?? $this->generator([]),
                new TeamPointsCalculator,
                new GameWinnerResolver,
            ),
        );
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
     * Oito placares determinísticos e distintos em que o time mandante sempre
     * vence, independentemente do embaralhamento.
     *
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
     * @return Collection<int, Game>
     */
    private function stageGames(Championship $championship, GameStage $stage): Collection
    {
        return $championship->games->filter(fn (Game $game) => $game->stage === $stage)->values();
    }

    /**
     * Fake sequencial de ScoreGenerator: devolve um placar por chamada, conta as
     * chamadas, pode falhar numa chamada configurável e lança se for chamada mais
     * vezes que o configurado.
     *
     * @param  array<int, GameScore>  $scores
     */
    private function generator(array $scores, ?int $failAt = null): ScoreGenerator
    {
        return new class($scores, $failAt) implements ScoreGenerator
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
                    throw new \LogicException('ScoreGenerator chamado mais vezes que o configurado.');
                }

                return $this->scores[$index];
            }
        };
    }
}
