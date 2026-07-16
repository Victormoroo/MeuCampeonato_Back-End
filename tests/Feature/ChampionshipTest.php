<?php

namespace Tests\Feature;

use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Models\Championship;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_championship_can_have_eight_teams(): void
    {
        $championship = Championship::factory()->create();

        Team::factory()
            ->count(8)
            ->for($championship)
            ->state(new Sequence(
                fn (Sequence $sequence) => [
                    'name' => 'Team '.($sequence->index + 1),
                    'registration_order' => $sequence->index + 1,
                ],
            ))
            ->create();

        $this->assertCount(8, $championship->teams);
        $this->assertDatabaseCount('teams', 8);
    }

    public function test_teams_can_be_retrieved_in_registration_order(): void
    {
        $championship = Championship::factory()->create();

        // Criados fora de ordem de propósito.
        foreach ([3, 1, 2] as $order) {
            Team::factory()->for($championship)->create([
                'name' => 'Team '.$order,
                'registration_order' => $order,
            ]);
        }

        $orders = $championship->teamsInRegistrationOrder
            ->pluck('registration_order')
            ->all();

        $this->assertSame([1, 2, 3], $orders);
    }

    public function test_a_championship_can_have_games(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->createTwoTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Quarterfinal,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $this->assertCount(1, $championship->games);
        $this->assertDatabaseCount('games', 1);
    }

    public function test_status_is_cast_to_championship_status_enum(): void
    {
        $championship = Championship::factory()->create();

        $this->assertInstanceOf(ChampionshipStatus::class, $championship->status);
        $this->assertSame(ChampionshipStatus::Pending, $championship->status);

        $championship->update(['status' => ChampionshipStatus::InProgress]);

        $this->assertSame(ChampionshipStatus::InProgress, $championship->fresh()->status);
        $this->assertDatabaseHas('championships', [
            'id' => $championship->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_deleting_a_championship_deletes_its_teams_and_games(): void
    {
        $championship = Championship::factory()->create();
        [$home, $away] = $this->createTwoTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Final,
            'sequence' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $championship->delete();

        $this->assertDatabaseCount('championships', 0);
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('games', 0);
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function createTwoTeams(Championship $championship): array
    {
        $home = Team::factory()->for($championship)->create([
            'name' => 'Home',
            'registration_order' => 1,
        ]);

        $away = Team::factory()->for($championship)->create([
            'name' => 'Away',
            'registration_order' => 2,
        ]);

        return [$home, $away];
    }
}
