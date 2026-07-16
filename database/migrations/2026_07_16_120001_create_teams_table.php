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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            // Ao excluir o campeonato, seus times são removidos.
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('registration_order');
            // Inteiro com sinal: points precisa aceitar valores negativos.
            $table->integer('points')->default(0);
            $table->unsignedTinyInteger('final_position')->nullable();
            $table->timestamps();

            // Unicidade dentro do mesmo campeonato. Em final_position, valores
            // NULL permanecem permitidos em múltiplas linhas (NULLs são distintos
            // em índices UNIQUE tanto no MySQL quanto no SQLite).
            $table->unique(['championship_id', 'name']);
            $table->unique(['championship_id', 'registration_order']);
            $table->unique(['championship_id', 'final_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
