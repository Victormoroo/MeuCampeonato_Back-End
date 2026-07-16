<?php

namespace Tests\Feature;

use App\Models\Championship;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_registration_order_in_the_same_championship_is_rejected_by_the_database(): void
    {
        $championship = Championship::factory()->create();

        Team::factory()->for($championship)->create([
            'name' => 'Team A',
            'registration_order' => 1,
        ]);

        $this->expectException(QueryException::class);

        Team::factory()->for($championship)->create([
            'name' => 'Team B',
            'registration_order' => 1,
        ]);
    }

    public function test_the_same_registration_order_is_allowed_across_different_championships(): void
    {
        $first = Championship::factory()->create();
        $second = Championship::factory()->create();

        $teamOne = Team::factory()->for($first)->create([
            'name' => 'Team A',
            'registration_order' => 1,
        ]);

        $teamTwo = Team::factory()->for($second)->create([
            'name' => 'Team A',
            'registration_order' => 1,
        ]);

        $this->assertDatabaseCount('teams', 2);
        $this->assertNotSame($teamOne->championship_id, $teamTwo->championship_id);
        $this->assertSame(1, $teamOne->registration_order);
        $this->assertSame(1, $teamTwo->registration_order);
    }

    public function test_points_accepts_negative_values(): void
    {
        $team = Team::factory()->create([
            'points' => -10,
        ]);

        $this->assertSame(-10, $team->fresh()->points);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'points' => -10,
        ]);
    }
}
