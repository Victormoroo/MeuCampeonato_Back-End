<?php

namespace App\Actions\Championships;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Domain\Championship\GameWinnerResolver;
use App\Domain\Championship\TeamPointsCalculator;
use App\Exceptions\GameAlreadyPlayedException;
use App\Exceptions\InvalidGameParticipantsException;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class PlayGame
{
    public function __construct(
        private readonly ScoreGenerator $scoreGenerator,
        private readonly TeamPointsCalculator $pointsCalculator,
        private readonly GameWinnerResolver $winnerResolver,
    ) {}

    public function handle(Game $game): Game
    {
        // Verificação rápida para o caso comum: evita executar o gerador externo
        // quando a partida já foi claramente disputada.
        if ($game->played_at !== null) {
            throw GameAlreadyPlayedException::forGame($game);
        }

        // O gerador (processo Python) roda FORA da transação, para não manter
        // locks de banco abertos durante a execução externa. Se falhar, nada foi
        // alterado ainda e a ScoreGenerationException apenas propaga.
        $score = $this->scoreGenerator->generate();

        return $this->play($game, $score);
    }

    /**
     * Dispute uma partida com um placar já gerado (não chama o ScoreGenerator).
     * Útil quando os placares foram pré-gerados fora da transação geral, como na
     * simulação completa do campeonato.
     */
    public function handleWithScore(Game $game, GameScore $score): Game
    {
        if ($game->played_at !== null) {
            throw GameAlreadyPlayedException::forGame($game);
        }

        return $this->play($game, $score);
    }

    /**
     * Fluxo compartilhado: valida, bloqueia, calcula, resolve o vencedor e
     * persiste tudo atomicamente. A DB::transaction interna funciona como
     * savepoint quando chamada dentro de uma transação maior.
     */
    private function play(Game $game, GameScore $score): Game
    {
        return DB::transaction(function () use ($game, $score): Game {
            $game = Game::query()
                ->whereKey($game->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Segunda verificação, agora protegida pelo lock (concorrência).
            if ($game->played_at !== null) {
                throw GameAlreadyPlayedException::forGame($game);
            }

            [$homeTeam, $awayTeam] = $this->lockParticipants($game);

            $homeVariation = $this->pointsCalculator->calculate($score->homeScore, $score->awayScore);
            $awayVariation = $this->pointsCalculator->calculate($score->awayScore, $score->homeScore);

            // Desempate usa os pontos acumulados ANTES da partida, portanto o
            // vencedor é resolvido antes de aplicar as variações.
            $outcome = $this->winnerResolver->resolve(
                $homeTeam,
                $awayTeam,
                $score->homeScore,
                $score->awayScore,
            );

            $homeTeam->points += $homeVariation;
            $awayTeam->points += $awayVariation;
            $homeTeam->save();
            $awayTeam->save();

            $game->fill([
                'home_score' => $score->homeScore,
                'away_score' => $score->awayScore,
                'winner_team_id' => $outcome->winner->id,
                'loser_team_id' => $outcome->loser->id,
                'played_at' => now(),
            ])->save();

            return $game->load(['championship', 'homeTeam', 'awayTeam', 'winner', 'loser']);
        });
    }

    /**
     * Valida e bloqueia (lockForUpdate) os dois times da partida, em ordem
     * crescente de id para reduzir risco de deadlock. Sempre usa as instâncias
     * recém-carregadas do banco, não os relacionamentos possivelmente obsoletos
     * da Game recebida.
     *
     * @return array{0: Team, 1: Team}
     */
    private function lockParticipants(Game $game): array
    {
        if ($game->home_team_id === null || $game->away_team_id === null) {
            throw InvalidGameParticipantsException::missingParticipant($game);
        }

        if ($game->home_team_id === $game->away_team_id) {
            throw InvalidGameParticipantsException::sameTeam($game);
        }

        $teams = Team::query()
            ->whereIn('id', [$game->home_team_id, $game->away_team_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $homeTeam = $teams->get($game->home_team_id);
        $awayTeam = $teams->get($game->away_team_id);

        if (! $homeTeam instanceof Team || ! $awayTeam instanceof Team) {
            throw InvalidGameParticipantsException::missingParticipant($game);
        }

        if ($homeTeam->championship_id !== $game->championship_id
            || $awayTeam->championship_id !== $game->championship_id) {
            throw InvalidGameParticipantsException::foreignParticipant($game);
        }

        return [$homeTeam, $awayTeam];
    }
}
