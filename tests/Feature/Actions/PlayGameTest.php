<?php

namespace Tests\Feature\Actions;

use App\Actions\Championships\PlayGame;
use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameOutcome;
use App\Domain\Championship\GameScore;
use App\Domain\Championship\GameWinnerResolver;
use App\Domain\Championship\TeamPointsCalculator;
use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Exceptions\GameAlreadyPlayedException;
use App\Exceptions\InvalidGameParticipantsException;
use App\Exceptions\ScoreGenerationException;
use App\Models\Championship;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_scores(): void
    {
        [, , , $game] = $this->makeGame();

        $result = $this->action($this->generator(3, 1))->handle($game);

        $this->assertSame(3, $result->home_score);
        $this->assertSame(1, $result->away_score);
        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'home_score' => 3,
            'away_score' => 1,
        ]);
    }

    public function test_it_persists_the_winner_and_loser(): void
    {
        [, $home, $away, $game] = $this->makeGame();

        $result = $this->action($this->generator(3, 1))->handle($game);

        $this->assertSame($home->id, $result->winner_team_id);
        $this->assertSame($away->id, $result->loser_team_id);
    }

    public function test_it_fills_played_at(): void
    {
        [, , , $game] = $this->makeGame();

        $result = $this->action($this->generator(2, 0))->handle($game);

        $this->assertNotNull($result->played_at);
        $this->assertDatabaseMissing('games', ['id' => $game->id, 'played_at' => null]);
    }

    public function test_it_returns_loaded_relationships(): void
    {
        [$championship, $home, $away, $game] = $this->makeGame();

        $result = $this->action($this->generator(3, 1))->handle($game);

        $this->assertTrue($result->relationLoaded('championship'));
        $this->assertTrue($result->relationLoaded('homeTeam'));
        $this->assertTrue($result->relationLoaded('awayTeam'));
        $this->assertTrue($result->relationLoaded('winner'));
        $this->assertTrue($result->relationLoaded('loser'));

        $this->assertTrue($result->championship->is($championship));
        $this->assertTrue($result->winner->is($home));
        $this->assertTrue($result->loser->is($away));
    }

    public function test_it_updates_points_from_a_3_to_1_result(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 0, awayPoints: 0);

        $this->action($this->generator(3, 1))->handle($game);

        $this->assertSame(2, $home->refresh()->points);
        $this->assertSame(-2, $away->refresh()->points);
    }

    public function test_it_adds_the_variation_to_the_existing_points(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 10, awayPoints: 5);

        $this->action($this->generator(3, 1))->handle($game);

        $this->assertSame(12, $home->refresh()->points);
        $this->assertSame(3, $away->refresh()->points);
    }

    public function test_on_a_draw_the_team_with_more_points_wins(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 10, awayPoints: 5);

        $result = $this->action($this->generator(2, 2))->handle($game);

        $this->assertSame($home->id, $result->winner_team_id);
        $this->assertSame($away->id, $result->loser_team_id);
    }

    public function test_on_a_score_and_points_tie_the_first_registered_wins(): void
    {
        [, $home, $away, $game] = $this->makeGame(
            homePoints: 5,
            awayPoints: 5,
            homeOrder: 1,
            awayOrder: 2,
        );

        $result = $this->action($this->generator(1, 1))->handle($game);

        $this->assertSame($home->id, $result->winner_team_id);
    }

    public function test_a_score_win_prevails_over_higher_previous_points(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 0, awayPoints: 100);

        $result = $this->action($this->generator(2, 1))->handle($game);

        $this->assertSame($home->id, $result->winner_team_id);
        $this->assertSame(1, $home->refresh()->points);
        $this->assertSame(99, $away->refresh()->points);
    }

    public function test_points_stay_the_same_on_a_draw(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 7, awayPoints: 3);

        $this->action($this->generator(1, 1))->handle($game);

        $this->assertSame(7, $home->refresh()->points);
        $this->assertSame(3, $away->refresh()->points);
    }

    public function test_the_generator_is_called_exactly_once(): void
    {
        [, , , $game] = $this->makeGame();
        $generator = $this->generator(3, 1);

        $this->action($generator)->handle($game);

        $this->assertSame(1, $generator->calls);
    }

    public function test_an_already_played_game_throws(): void
    {
        [, , , $game] = $this->makePlayedGame();

        $this->expectException(GameAlreadyPlayedException::class);

        $this->action($this->generator(3, 1))->handle($game);
    }

    public function test_an_already_played_game_does_not_call_the_generator(): void
    {
        [, , , $game] = $this->makePlayedGame();
        $generator = $this->generator(3, 1);

        try {
            $this->action($generator)->handle($game);
            $this->fail('Expected GameAlreadyPlayedException.');
        } catch (GameAlreadyPlayedException) {
            // esperado
        }

        $this->assertSame(0, $generator->calls);
    }

    public function test_the_same_team_on_both_sides_throws(): void
    {
        $championship = Championship::factory()->create();
        $team = Team::factory()->for($championship)->create(['registration_order' => 1]);

        $game = $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $team->id,
            'away_team_id' => $team->id,
        ]);

        $this->expectException(InvalidGameParticipantsException::class);

        $this->action($this->generator(3, 1))->handle($game);
    }

    public function test_a_team_from_another_championship_throws(): void
    {
        $championship = Championship::factory()->create();
        $home = Team::factory()->for($championship)->create(['registration_order' => 1]);

        $other = Championship::factory()->create();
        $foreign = Team::factory()->for($other)->create(['registration_order' => 1]);

        $game = $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $foreign->id,
        ]);

        $this->expectException(InvalidGameParticipantsException::class);

        $this->action($this->generator(3, 1))->handle($game);
    }

    public function test_a_generator_failure_changes_nothing(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 4, awayPoints: 6);

        try {
            $this->action($this->generator(3, 1, shouldFail: true))->handle($game);
            $this->fail('Expected ScoreGenerationException.');
        } catch (ScoreGenerationException) {
            // esperado
        }

        $game->refresh();
        $this->assertNull($game->home_score);
        $this->assertNull($game->away_score);
        $this->assertNull($game->winner_team_id);
        $this->assertNull($game->loser_team_id);
        $this->assertNull($game->played_at);
        $this->assertSame(4, $home->refresh()->points);
        $this->assertSame(6, $away->refresh()->points);
    }

    public function test_it_does_not_change_stage_sequence_status_or_final_position(): void
    {
        [$championship, $home, $away, $game] = $this->makeGame();

        $this->action($this->generator(3, 1))->handle($game);

        $game->refresh();
        $this->assertSame(GameStage::Final, $game->stage);
        $this->assertSame(1, $game->sequence);

        $this->assertSame(ChampionshipStatus::Pending, $championship->refresh()->status);
        $this->assertNull($home->refresh()->final_position);
        $this->assertNull($away->refresh()->final_position);
    }

    public function test_only_the_first_of_two_stale_instances_persists(): void
    {
        [, , , $game] = $this->makeGame();

        $first = Game::findOrFail($game->id);
        $second = Game::findOrFail($game->id);

        $this->action($this->generator(3, 1))->handle($first);

        try {
            $this->action($this->generator(5, 5))->handle($second);
            $this->fail('Expected GameAlreadyPlayedException on the second run.');
        } catch (GameAlreadyPlayedException) {
            // esperado: bloqueado pela verificação após recuperar a versão atual
        }

        $game->refresh();
        $this->assertSame(3, $game->home_score);
        $this->assertSame(1, $game->away_score);
    }

    public function test_negative_points_keep_accumulating(): void
    {
        [, $home, , $game] = $this->makeGame(homePoints: -5, awayPoints: 0);

        $this->action($this->generator(1, 3))->handle($game);

        // home perde por 1 x 3 => variação -2 => -5 + (-2) = -7
        $this->assertSame(-7, $home->refresh()->points);
    }

    public function test_the_loser_is_always_the_other_participant(): void
    {
        [, $home, $away, $game] = $this->makeGame();

        $result = $this->action($this->generator(0, 4))->handle($game);

        $this->assertSame($away->id, $result->winner_team_id);
        $this->assertSame($home->id, $result->loser_team_id);
        $this->assertNotSame($result->winner_team_id, $result->loser_team_id);
    }

    public function test_it_rolls_back_every_change_when_persistence_fails(): void
    {
        [, $home, $away, $game] = $this->makeGame(homePoints: 5, awayPoints: 3);

        // Resolver controlado: aponta o vencedor para um time inexistente,
        // provocando falha de foreign key ao salvar a partida — DEPOIS de os
        // pontos dos times já terem sido salvos dentro da transação.
        $brokenResolver = new class extends GameWinnerResolver
        {
            public function resolve(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): GameOutcome
            {
                $ghost = new Team;
                $ghost->id = PHP_INT_MAX;

                return new GameOutcome($ghost, $awayTeam);
            }
        };

        try {
            $this->action($this->generator(3, 1), $brokenResolver)->handle($game);
            $this->fail('Expected a persistence failure.');
        } catch (QueryException) {
            // esperado: falha de persistência (foreign key)
        }

        $game->refresh();
        $this->assertNull($game->home_score);
        $this->assertNull($game->away_score);
        $this->assertNull($game->winner_team_id);
        $this->assertNull($game->loser_team_id);
        $this->assertNull($game->played_at);

        $this->assertSame(5, $home->refresh()->points);
        $this->assertSame(3, $away->refresh()->points);
    }

    private function action(ScoreGenerator $generator, ?GameWinnerResolver $resolver = null): PlayGame
    {
        return new PlayGame(
            $generator,
            new TeamPointsCalculator,
            $resolver ?? new GameWinnerResolver,
        );
    }

    /**
     * Fake determinística de ScoreGenerator que conta as chamadas e,
     * opcionalmente, falha.
     */
    private function generator(int $home, int $away, bool $shouldFail = false): ScoreGenerator
    {
        return new class(new GameScore($home, $away), $shouldFail) implements ScoreGenerator
        {
            public int $calls = 0;

            public function __construct(
                private readonly GameScore $score,
                private readonly bool $shouldFail,
            ) {}

            public function generate(): GameScore
            {
                $this->calls++;

                if ($this->shouldFail) {
                    throw ScoreGenerationException::processFailed();
                }

                return $this->score;
            }
        };
    }

    /**
     * @return array{0: Championship, 1: Team, 2: Team, 3: Game}
     */
    private function makeGame(
        int $homePoints = 0,
        int $awayPoints = 0,
        int $homeOrder = 1,
        int $awayOrder = 2,
    ): array {
        $championship = Championship::factory()->create();

        $home = Team::factory()->for($championship)->create([
            'name' => 'Home',
            'registration_order' => $homeOrder,
            'points' => $homePoints,
        ]);
        $away = Team::factory()->for($championship)->create([
            'name' => 'Away',
            'registration_order' => $awayOrder,
            'points' => $awayPoints,
        ]);

        $game = $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        return [$championship, $home, $away, $game];
    }

    /**
     * @return array{0: Championship, 1: Team, 2: Team, 3: Game}
     */
    private function makePlayedGame(): array
    {
        [$championship, $home, $away, $game] = $this->makeGame();

        $game->update([
            'home_score' => 1,
            'away_score' => 0,
            'winner_team_id' => $home->id,
            'loser_team_id' => $away->id,
            'played_at' => now(),
        ]);

        return [$championship, $home, $away, $game];
    }
}
