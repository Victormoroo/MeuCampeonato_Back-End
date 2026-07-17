<?php

namespace App\Providers;

use App\Contracts\ScoreGenerator;
use App\Infrastructure\Score\PythonScoreGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: o gerador é imutável (apenas configuração) e sem estado,
        // então uma única instância pode ser reutilizada com segurança.
        $this->app->singleton(ScoreGenerator::class, function (): PythonScoreGenerator {
            return new PythonScoreGenerator(
                (string) config('championship.python_binary'),
                (string) config('championship.script_path'),
                (int) config('championship.process_timeout'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
