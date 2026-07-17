<?php

namespace Tests\Feature\Integration;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use Tests\TestCase;

class PythonScoreGeneratorIntegrationTest extends TestCase
{
    /**
     * Executa de verdade o teste.py dentro do contêiner, provando que PHP,
     * Laravel, Process, Python e o script funcionam juntos. Sem Process::fake,
     * sem banco. Executa o script uma única vez.
     */
    public function test_it_runs_the_real_python_script_and_returns_a_valid_score(): void
    {
        $score = app(ScoreGenerator::class)->generate();

        $this->assertInstanceOf(GameScore::class, $score);

        $this->assertGreaterThanOrEqual(0, $score->homeScore);
        $this->assertLessThanOrEqual(7, $score->homeScore);

        $this->assertGreaterThanOrEqual(0, $score->awayScore);
        $this->assertLessThanOrEqual(7, $score->awayScore);
    }
}
