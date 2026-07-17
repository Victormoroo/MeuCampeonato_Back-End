<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ScoreGenerationException extends RuntimeException
{
    public static function processFailed(?Throwable $previous = null): self
    {
        return new self('Não foi possível executar o gerador de placar.', 0, $previous);
    }

    public static function timedOut(?Throwable $previous = null): self
    {
        return new self('O gerador de placar excedeu o tempo limite.', 0, $previous);
    }

    public static function invalidOutput(): self
    {
        return new self('O gerador de placar retornou um resultado inválido.');
    }
}
