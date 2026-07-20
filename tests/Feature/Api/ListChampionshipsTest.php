<?php

namespace Tests\Feature\Api;

use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Models\Championship;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ListChampionshipsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/championships';

    public function test_it_responds_200_with_pagination_envelope(): void
    {
        Championship::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'status', 'started_at', 'completed_at', 'created_at', 'updated_at', 'teams_count', 'games_count'],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
        ]);
    }

    public function test_it_orders_championships_from_newest_to_oldest_by_id(): void
    {
        $ids = Championship::factory()->count(3)->create()->pluck('id')->sort()->values();

        $response = $this->getJson(self::ENDPOINT);

        $returned = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame($ids->reverse()->values()->all(), $returned);
    }

    public function test_it_paginates_with_15_items_per_page_and_the_second_page_works(): void
    {
        Championship::factory()->count(20)->create();

        $page1 = $this->getJson(self::ENDPOINT);
        $page1->assertOk();
        $page1->assertJsonCount(15, 'data');
        $page1->assertJsonPath('meta.per_page', 15);
        $page1->assertJsonPath('meta.total', 20);
        $page1->assertJsonPath('meta.current_page', 1);
        $page1->assertJsonPath('meta.last_page', 2);
        $this->assertSame(Championship::max('id'), $page1->json('data.0.id'));

        $page2 = $this->getJson(self::ENDPOINT.'?page=2');
        $page2->assertOk();
        $page2->assertJsonCount(5, 'data');
        $page2->assertJsonPath('meta.current_page', 2);
        $this->assertSame(Championship::min('id'), $page2->json('data.4.id'));
    }

    public function test_it_returns_teams_count_and_games_count(): void
    {
        $championship = Championship::factory()->create();
        $teams = $this->createTeams($championship);

        $championship->games()->create([
            'stage' => GameStage::Quarterfinal,
            'sequence' => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
        ]);
        $championship->games()->create([
            'stage' => GameStage::Quarterfinal,
            'sequence' => 2,
            'home_team_id' => $teams[2]->id,
            'away_team_id' => $teams[3]->id,
        ]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $response->assertJsonPath('data.0.teams_count', 8);
        $response->assertJsonPath('data.0.games_count', 2);
    }

    public function test_it_does_not_include_full_teams_and_games_collections(): void
    {
        $championship = Championship::factory()->create();
        $this->createTeams($championship);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertArrayHasKey('teams_count', $item);
        $this->assertArrayHasKey('games_count', $item);
        $this->assertArrayNotHasKey('teams', $item);
        $this->assertArrayNotHasKey('games', $item);
    }

    public function test_it_returns_an_empty_list_with_valid_pagination_when_there_are_no_championships(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function test_it_includes_pending_in_progress_and_completed_championships(): void
    {
        Championship::factory()->create(['status' => ChampionshipStatus::Pending]);
        Championship::factory()->create(['status' => ChampionshipStatus::InProgress]);
        Championship::factory()->create(['status' => ChampionshipStatus::Completed]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertContains('pending', $statuses);
        $this->assertContains('in_progress', $statuses);
        $this->assertContains('completed', $statuses);
    }

    public function test_it_includes_the_champion_name_for_a_completed_championship(): void
    {
        $championship = Championship::factory()->create(['status' => ChampionshipStatus::Completed]);
        Team::factory()->for($championship)->create([
            'name' => 'Campeão FC',
            'registration_order' => 1,
            'final_position' => 1,
        ]);
        Team::factory()->for($championship)->create([
            'name' => 'Vice FC',
            'registration_order' => 2,
            'final_position' => 2,
        ]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $response->assertJsonPath('data.0.champion', 'Campeão FC');
    }

    public function test_champion_is_null_when_there_is_no_champion_yet(): void
    {
        $championship = Championship::factory()->create(['status' => ChampionshipStatus::Pending]);
        Team::factory()->for($championship)->create([
            'name' => 'Sem Posição',
            'registration_order' => 1,
        ]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertArrayHasKey('champion', $item);
        $this->assertNull($item['champion']);
    }

    /**
     * @return Collection<int, Team>
     */
    private function createTeams(Championship $championship, int $count = 8): Collection
    {
        return Team::factory()
            ->for($championship)
            ->count($count)
            ->state(new Sequence(
                fn (Sequence $sequence) => [
                    'name' => 'Team '.($sequence->index + 1),
                    'registration_order' => $sequence->index + 1,
                ],
            ))
            ->create();
    }
}
