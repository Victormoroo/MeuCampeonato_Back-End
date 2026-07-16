<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['championship_id', 'name', 'registration_order', 'points', 'final_position'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_order' => 'integer',
            'points' => 'integer',
            'final_position' => 'integer',
        ];
    }

    /** @return BelongsTo<Championship, $this> */
    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class);
    }

    /** @return HasMany<Game, $this> */
    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    /** @return HasMany<Game, $this> */
    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /** @return HasMany<Game, $this> */
    public function wonGames(): HasMany
    {
        return $this->hasMany(Game::class, 'winner_team_id');
    }

    /** @return HasMany<Game, $this> */
    public function lostGames(): HasMany
    {
        return $this->hasMany(Game::class, 'loser_team_id');
    }
}
