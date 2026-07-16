<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            // Ao excluir o campeonato, seus jogos são removidos.
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            // Guardado como string; o model converte para o enum GameStage.
            $table->string('stage');
            $table->unsignedTinyInteger('sequence');
            // restrictOnDelete: a exclusão isolada de um time é bloqueada pelo
            // banco, preservando o histórico das partidas (não usa CASCADE aqui
            // para não destruir jogos ao remover um time). SET NULL não é opção
            // para home/away porque essas colunas não são nullable.
            $table->foreignId('home_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->restrictOnDelete();
            // Placares nullable enquanto a partida não foi disputada.
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->foreignId('loser_team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->unique(['championship_id', 'stage', 'sequence']);
            $table->index(['championship_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
