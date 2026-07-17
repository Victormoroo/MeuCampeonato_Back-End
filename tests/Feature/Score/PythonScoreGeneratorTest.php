<?php

namespace Tests\Feature\Score;

use App\Contracts\ScoreGenerator;
use App\Domain\Championship\GameScore;
use App\Exceptions\ScoreGenerationException;
use App\Infrastructure\Score\PythonScoreGenerator;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PythonScoreGeneratorTest extends TestCase
{
    private const BINARY = 'python3';

    private const SCRIPT = '/var/www/html/teste.py';

    private const TIMEOUT = 5;

    private function generator(): PythonScoreGenerator
    {
        return new PythonScoreGenerator(self::BINARY, self::SCRIPT, self::TIMEOUT);
    }

    private function fakeProcess(string $output, int $exitCode = 0): void
    {
        Process::fake([
            '*' => Process::result(output: $output, exitCode: $exitCode),
        ]);
    }

    public function test_it_parses_two_scores(): void
    {
        $this->fakeProcess("0\n2\n");

        $score = $this->generator()->generate();

        $this->assertInstanceOf(GameScore::class, $score);
        $this->assertSame(0, $score->homeScore);
        $this->assertSame(2, $score->awayScore);
    }

    public function test_it_accepts_the_upper_bounds(): void
    {
        $this->fakeProcess("7\n7\n");

        $score = $this->generator()->generate();

        $this->assertSame(7, $score->homeScore);
        $this->assertSame(7, $score->awayScore);
    }

    public function test_it_handles_surrounding_spaces_and_crlf(): void
    {
        $this->fakeProcess("  3 \r\n 5  \r\n");

        $score = $this->generator()->generate();

        $this->assertSame(3, $score->homeScore);
        $this->assertSame(5, $score->awayScore);
    }

    public function test_it_tolerates_empty_lines_at_the_extremities(): void
    {
        $this->fakeProcess("\n\n1\n4\n\n");

        $score = $this->generator()->generate();

        $this->assertSame(1, $score->homeScore);
        $this->assertSame(4, $score->awayScore);
    }

    public function test_a_non_zero_exit_code_throws(): void
    {
        $this->fakeProcess('', 1);

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_a_single_value_throws(): void
    {
        $this->fakeProcess("3\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_three_values_throw(): void
    {
        $this->fakeProcess("1\n2\n3\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_text_output_throws(): void
    {
        $this->fakeProcess("foo\nbar\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_decimal_output_throws(): void
    {
        $this->fakeProcess("1.5\n2\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_negative_output_throws(): void
    {
        $this->fakeProcess("-1\n2\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_a_value_above_seven_throws(): void
    {
        $this->fakeProcess("8\n2\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_an_empty_line_between_scores_throws(): void
    {
        $this->fakeProcess("1\n\n2\n");

        $this->expectException(ScoreGenerationException::class);

        $this->generator()->generate();
    }

    public function test_the_command_uses_the_binary_and_absolute_script_path(): void
    {
        $this->fakeProcess("0\n0\n");

        $this->generator()->generate();

        Process::assertRan(fn ($process) => $process->command === [self::BINARY, self::SCRIPT]);
    }

    public function test_the_process_uses_the_configured_timeout(): void
    {
        $this->fakeProcess("0\n0\n");

        $this->generator()->generate();

        Process::assertRan(fn ($process) => $process->timeout === self::TIMEOUT);
    }

    public function test_the_container_resolves_the_python_generator(): void
    {
        $this->assertInstanceOf(PythonScoreGenerator::class, app(ScoreGenerator::class));
    }
}
