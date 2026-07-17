<?php

namespace Tests\Unit\Domain\Championship;

use App\Domain\Championship\TeamPointsCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TeamPointsCalculatorTest extends TestCase
{
    private TeamPointsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TeamPointsCalculator;
    }

    public function test_a_win_of_3_to_1_returns_plus_2(): void
    {
        $this->assertSame(2, $this->calculator->calculate(3, 1));
    }

    public function test_a_loss_of_1_to_3_returns_minus_2(): void
    {
        $this->assertSame(-2, $this->calculator->calculate(1, 3));
    }

    public function test_a_draw_of_2_to_2_returns_0(): void
    {
        $this->assertSame(0, $this->calculator->calculate(2, 2));
    }

    public function test_a_draw_of_0_to_0_returns_0(): void
    {
        $this->assertSame(0, $this->calculator->calculate(0, 0));
    }

    public function test_a_thrashing_of_7_to_0_returns_plus_7(): void
    {
        $this->assertSame(7, $this->calculator->calculate(7, 0));
    }

    public function test_both_participants_get_symmetric_points(): void
    {
        $this->assertSame(
            $this->calculator->calculate(3, 1),
            -$this->calculator->calculate(1, 3),
        );
    }

    public function test_negative_goals_scored_throw_an_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(-1, 0);
    }

    public function test_negative_goals_conceded_throw_an_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(0, -1);
    }
}
