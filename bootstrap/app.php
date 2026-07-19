<?php

use App\Exceptions\ChampionshipCannotBeSimulatedException;
use App\Exceptions\ScoreGenerationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Conflito de estado do campeonato (não pendente, times != 8, já tem
        // partidas): 409, apenas com a mensagem da exceção.
        $exceptions->render(fn (ChampionshipCannotBeSimulatedException $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_CONFLICT,
        ));

        // Falha no processo externo de geração de placar: 502, com a mensagem
        // genérica já definida na exceção (sem detalhes internos).
        $exceptions->render(fn (ScoreGenerationException $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_BAD_GATEWAY,
        ));
    })->create();
