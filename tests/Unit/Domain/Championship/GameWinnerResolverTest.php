<?php

namespace Tests\Unit\Domain\Championship;

use App\Domain\Championship\GameOutcome;
use App\Domain\Championship\GameWinnerResolver;
use App\Models\Team;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GameWinnerResolverTest extends TestCase
{
    private GameWinnerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new GameWinnerResolver;
    }

    public function test_home_wins_when_it_scores_more_goals(): void
    {
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 0, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 3, 1);

        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_away_wins_when_it_scores_more_goals(): void
    {
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 0, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 1, 2);

        $this->assertSame($away, $outcome->winner);
        $this->assertSame($home, $outcome->loser);
    }

    public function test_a_win_on_the_scoreboard_prevails_over_accumulated_points(): void
    {
        // Home tem MENOS pontos acumulados, mas venceu no placar.
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 100, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 2, 1);

        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_on_a_draw_home_wins_with_more_points(): void
    {
        $home = $this->makeTeam('Home', points: 10, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 5, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 2, 2);

        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_on_a_draw_away_wins_with_more_points(): void
    {
        $home = $this->makeTeam('Home', points: 5, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 10, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 2, 2);

        $this->assertSame($away, $outcome->winner);
        $this->assertSame($home, $outcome->loser);
    }

    public function test_negative_points_are_compared_correctly(): void
    {
        // -1 é maior que -3, portanto Home vence no empate de placar.
        $home = $this->makeTeam('Home', points: -1, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: -3, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 0, 0);

        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_on_a_score_and_points_tie_home_wins_when_registered_first(): void
    {
        $home = $this->makeTeam('Home', points: 5, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 5, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 1, 1);

        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_on_a_score_and_points_tie_away_wins_when_registered_first(): void
    {
        $home = $this->makeTeam('Home', points: 5, registrationOrder: 5);
        $away = $this->makeTeam('Away', points: 5, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 1, 1);

        $this->assertSame($away, $outcome->winner);
        $this->assertSame($home, $outcome->loser);
    }

    public function test_game_outcome_contains_the_correct_winner_and_loser(): void
    {
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 0, registrationOrder: 2);

        $outcome = $this->resolver->resolve($home, $away, 5, 0);

        $this->assertInstanceOf(GameOutcome::class, $outcome);
        $this->assertSame($home, $outcome->winner);
        $this->assertSame($away, $outcome->loser);
    }

    public function test_a_negative_home_score_throws_an_exception(): void
    {
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 0, registrationOrder: 2);

        $this->expectException(InvalidArgumentException::class);

        $this->resolver->resolve($home, $away, -1, 0);
    }

    public function test_a_negative_away_score_throws_an_exception(): void
    {
        $home = $this->makeTeam('Home', points: 0, registrationOrder: 1);
        $away = $this->makeTeam('Away', points: 0, registrationOrder: 2);

        $this->expectException(InvalidArgumentException::class);

        $this->resolver->resolve($home, $away, 0, -1);
    }

    private function makeTeam(string $name, int $points, int $registrationOrder): Team
    {
        $team = new Team;
        $team->name = $name;
        $team->points = $points;
        $team->registration_order = $registrationOrder;

        return $team;
    }
}
