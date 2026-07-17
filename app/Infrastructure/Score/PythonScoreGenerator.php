<?php

namespace App\Infrastructure\Score;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Exceptions\ScoreGenerationException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;

class PythonScoreGenerator implements ScoreGenerator
{
    public function __construct(
        private readonly string $pythonBinary,
        private readonly string $scriptPath,
        private readonly int $timeout,
    ) {}

    public function generate(): GameScore
    {
        try {
            $result = Process::timeout($this->timeout)->run([
                $this->pythonBinary,
                $this->scriptPath,
            ]);
        } catch (ProcessTimedOutException $exception) {
            throw ScoreGenerationException::timedOut($exception);
        } catch (Throwable $exception) {
            throw ScoreGenerationException::processFailed($exception);
        }

        if ($result->failed()) {
            throw ScoreGenerationException::processFailed();
        }

        return $this->parse($result->output());
    }

    private function parse(string $output): GameScore
    {
        // trim() no output inteiro remove linhas vazias apenas das extremidades
        // (e finais de linha Unix ou Windows); linhas vazias entre os valores são
        // preservadas e, por gerarem mais de duas linhas, invalidam o output.
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        if (count($lines) !== 2) {
            throw ScoreGenerationException::invalidOutput();
        }

        return new GameScore(
            $this->toScore(trim($lines[0])),
            $this->toScore(trim($lines[1])),
        );
    }

    private function toScore(string $value): int
    {
        // Apenas inteiros decimais não negativos (rejeita negativos, decimais e texto).
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw ScoreGenerationException::invalidOutput();
        }

        $score = (int) $value;

        if ($score > 7) {
            throw ScoreGenerationException::invalidOutput();
        }

        return $score;
    }
}
