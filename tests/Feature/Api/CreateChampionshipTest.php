<?php

namespace Tests\Feature\Api;

use App\Enums\ChampionshipStatus;
use App\Models\Championship;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChampionshipTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/championships';

    /**
     * @return list<string>
     */
    private function eightTeams(): array
    {
        return ['Águias', 'Tigres', 'Leões', 'Panteras', 'Falcões', 'Lobos', 'Dragões', 'Tubarões'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Copa do Bairro',
            'teams' => $this->eightTeams(),
        ], $overrides);
    }

    private function assertNothingPersisted(): void
    {
        $this->assertDatabaseCount('championships', 0);
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_it_creates_a_championship_with_exactly_eight_teams(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertCreated(); // HTTP 201
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'status',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
                'teams' => [
                    ['id', 'name', 'registration_order', 'points', 'final_position'],
                ],
            ],
        ]);

        $response->assertJsonPath('data.name', 'Copa do Bairro');
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.started_at', null);
        $response->assertJsonPath('data.completed_at', null);
        $response->assertJsonCount(8, 'data.teams');

        $this->assertDatabaseCount('championships', 1);
        $this->assertDatabaseCount('teams', 8);

        $championship = Championship::sole();
        $this->assertSame(ChampionshipStatus::Pending, $championship->status);
        $this->assertNull($championship->started_at);
        $this->assertNull($championship->completed_at);
    }

    public function test_team_resource_does_not_expose_championship_id(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertCreated();

        $team = $response->json('data.teams.0');
        $this->assertArrayHasKey('registration_order', $team);
        $this->assertArrayNotHasKey('championship_id', $team);
    }

    public function test_registration_order_follows_the_received_order(): void
    {
        $teams = $this->eightTeams();

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertCreated();

        foreach ($teams as $index => $name) {
            $response->assertJsonPath("data.teams.$index.name", $name);
            $response->assertJsonPath("data.teams.$index.registration_order", $index + 1);

            $this->assertDatabaseHas('teams', [
                'name' => $name,
                'registration_order' => $index + 1,
            ]);
        }
    }

    public function test_teams_start_with_zero_points_and_null_final_position(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.teams.0.points', 0);
        $response->assertJsonPath('data.teams.0.final_position', null);

        $this->assertSame(8, Team::where('points', 0)->count());
        $this->assertSame(8, Team::whereNull('final_position')->count());
    }

    public function test_trim_normalization_is_reflected_in_persisted_data(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => '  Copa do Bairro  ',
            'teams' => ['  Águias  ', 'Tigres', 'Leões', 'Panteras', 'Falcões', 'Lobos', 'Dragões', 'Tubarões'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Copa do Bairro');
        $response->assertJsonPath('data.teams.0.name', 'Águias');

        $this->assertDatabaseHas('championships', ['name' => 'Copa do Bairro']);
        $this->assertDatabaseHas('teams', ['name' => 'Águias', 'registration_order' => 1]);
        $this->assertDatabaseMissing('teams', ['name' => '  Águias  ']);
    }

    public function test_it_rejects_seven_teams(): void
    {
        $teams = $this->eightTeams();
        array_pop($teams); // 7 times

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams');
        $this->assertNothingPersisted();
    }

    public function test_it_rejects_nine_teams(): void
    {
        $teams = $this->eightTeams();
        $teams[] = 'Cobras'; // 9 times

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams');
        $this->assertNothingPersisted();
    }

    public function test_it_rejects_duplicate_team_names(): void
    {
        $teams = ['Águias', 'Águias', 'Leões', 'Panteras', 'Falcões', 'Lobos', 'Dragões', 'Tubarões'];

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams.1');
        $this->assertNothingPersisted();
    }

    public function test_it_rejects_duplicate_team_names_ignoring_case(): void
    {
        $teams = ['Águias', 'ÁGUIAS', 'Leões', 'Panteras', 'Falcões', 'Lobos', 'Dragões', 'Tubarões'];

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams.1');
        $this->assertNothingPersisted();
    }

    public function test_it_rejects_duplicates_hidden_by_surrounding_spaces(): void
    {
        $teams = ['Águias', '   Águias   ', 'Leões', 'Panteras', 'Falcões', 'Lobos', 'Dragões', 'Tubarões'];

        $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams.1');
        $this->assertNothingPersisted();
    }

    public function test_it_rejects_a_blank_championship_name(): void
    {
        foreach (['', '   '] as $name) {
            $response = $this->postJson(self::ENDPOINT, $this->payload(['name' => $name]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('name');
        }

        $this->assertNothingPersisted();
    }

    public function test_it_rejects_a_blank_team_name(): void
    {
        foreach (['', '   '] as $blank) {
            $teams = $this->eightTeams();
            $teams[3] = $blank;

            $response = $this->postJson(self::ENDPOINT, $this->payload(['teams' => $teams]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('teams.3');
        }

        $this->assertNothingPersisted();
    }

    public function test_it_requires_the_name(): void
    {
        $payload = $this->payload();
        unset($payload['name']);

        $response = $this->postJson(self::ENDPOINT, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
        $this->assertNothingPersisted();
    }

    public function test_it_requires_the_teams(): void
    {
        $payload = $this->payload();
        unset($payload['teams']);

        $response = $this->postJson(self::ENDPOINT, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('teams');
        $this->assertNothingPersisted();
    }

    public function test_the_endpoint_answers_as_json_without_html_redirect(): void
    {
        // Requisição SEM cabeçalho "Accept: application/json" de propósito:
        // como a rota é de API, a falha de validação deve responder em JSON (422),
        // e não com um redirecionamento HTML (302).
        $response = $this->post(self::ENDPOINT, []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertNothingPersisted();
    }
}
