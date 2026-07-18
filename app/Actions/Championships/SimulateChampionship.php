<?php

namespace App\Actions\Championships;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Enums\ChampionshipStatus;
use App\Enums\GameStage;
use App\Exceptions\ChampionshipCannotBeSimulatedException;
use App\Models\Championship;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimulateChampionship
{
    public function __construct(
        private readonly ScoreGenerator $scoreGenerator,
        private readonly PlayGame $playGame,
    ) {}

    public function handle(Championship $championship): Championship
    {
        // Validação preliminar (sem lock): evita gerar oito placares quando o
        // campeonato já é claramente inválido.
        $this->assertCanBeSimulated($championship);

        // Todos os oito placares são gerados FORA de qualquer transação. Se
        // qualquer geração falhar, a exceção propaga e nada é alterado.
        $scores = $this->generateScores();

        return DB::transaction(function () use ($championship, $scores): Championship {
            $championship = Championship::query()
                ->whereKey($championship->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Revalida sob lock (protege contra simulações concorrentes).
            $this->assertCanBeSimulated($championship);

            $teams = $championship->teams()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($teams->count() !== 8) {
                throw ChampionshipCannotBeSimulatedException::wrongTeamCount($teams->count());
            }

            $championship->update([
                'status' => ChampionshipStatus::InProgress,
                'started_at' => now(),
                'completed_at' => null,
            ]);

            // Quartas: embaralha os oito times e forma quatro pares.
            $quarterTeams = $teams->shuffle()->values();
            $quarterWinners = new Collection;

            for ($i = 0; $i < 4; $i++) {
                $game = $this->playMatch(
                    $championship,
                    GameStage::Quarterfinal,
                    $i + 1,
                    $quarterTeams[$i * 2],
                    $quarterTeams[$i * 2 + 1],
                    $scores[$i],
                );

                $quarterWinners->push($game->winner);
            }

            // Semifinais: embaralha novamente os quatro vencedores.
            $semiTeams = $quarterWinners->shuffle()->values();
            $finalists = new Collection;
            $semifinalLosers = new Collection;

            for ($i = 0; $i < 2; $i++) {
                $game = $this->playMatch(
                    $championship,
                    GameStage::Semifinal,
                    $i + 1,
                    $semiTeams[$i * 2],
                    $semiTeams[$i * 2 + 1],
                    $scores[4 + $i],
                );

                $finalists->push($game->winner);
                $semifinalLosers->push($game->loser);
            }

            // Terceiro lugar (criado antes da final): perdedores das semifinais.
            $thirdPlace = $semifinalLosers->shuffle()->values();
            $thirdPlaceGame = $this->playMatch(
                $championship,
                GameStage::ThirdPlace,
                1,
                $thirdPlace[0],
                $thirdPlace[1],
                $scores[6],
            );

            // Final: vencedores das semifinais.
            $final = $finalists->shuffle()->values();
            $finalGame = $this->playMatch(
                $championship,
                GameStage::Final,
                1,
                $final[0],
                $final[1],
                $scores[7],
            );

            $this->assignPosition($finalGame->winner, 1);
            $this->assignPosition($finalGame->loser, 2);
            $this->assignPosition($thirdPlaceGame->winner, 3);
            $this->assignPosition($thirdPlaceGame->loser, 4);

            $championship->update([
                'status' => ChampionshipStatus::Completed,
                'completed_at' => now(),
            ]);

            return $championship->load([
                'teams' => fn ($query) => $query->orderBy('registration_order'),
                'games' => fn ($query) => $query->orderBy('id'),
                'games.homeTeam',
                'games.awayTeam',
                'games.winner',
                'games.loser',
            ]);
        });
    }

    private function assertCanBeSimulated(Championship $championship): void
    {
        if ($championship->status !== ChampionshipStatus::Pending) {
            throw ChampionshipCannotBeSimulatedException::notPending($championship);
        }

        $teamCount = $championship->teams()->count();

        if ($teamCount !== 8) {
            throw ChampionshipCannotBeSimulatedException::wrongTeamCount($teamCount);
        }

        if ($championship->games()->exists()) {
            throw ChampionshipCannotBeSimulatedException::alreadyHasGames($championship);
        }
    }

    /**
     * @return array<int, GameScore>
     */
    private function generateScores(): array
    {
        $scores = [];

        for ($i = 0; $i < 8; $i++) {
            $scores[] = $this->scoreGenerator->generate();
        }

        return $scores;
    }

    private function playMatch(
        Championship $championship,
        GameStage $stage,
        int $sequence,
        Team $home,
        Team $away,
        GameScore $score,
    ): Game {
        $game = $championship->games()->create([
            'stage' => $stage,
            'sequence' => $sequence,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        return $this->playGame->handleWithScore($game, $score);
    }

    private function assignPosition(Team $team, int $position): void
    {
        // Atualização direcionada apenas de final_position (não toca em points).
        Team::whereKey($team->id)->update(['final_position' => $position]);
    }
}
