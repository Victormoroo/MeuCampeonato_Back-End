<?php

namespace App\Models;

use App\Enums\ChampionshipStatus;
use Database\Factories\ChampionshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'status', 'started_at', 'completed_at'])]
class Championship extends Model
{
    /** @use HasFactory<ChampionshipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChampionshipStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Times recuperáveis pela ordem de inscrição.
     *
     * @return HasMany<Team, $this>
     */
    public function teamsInRegistrationOrder(): HasMany
    {
        return $this->teams()->orderBy('registration_order');
    }

    /** @return HasMany<Game, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * O time campeão (final_position = 1), presente quando o campeonato foi
     * concluído.
     *
     * @return HasOne<Team, $this>
     */
    public function champion(): HasOne
    {
        return $this->hasOne(Team::class)->where('final_position', 1);
    }
}
