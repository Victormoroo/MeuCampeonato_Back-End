<?php

namespace App\Models;

use App\Enums\GameStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'championship_id',
    'stage',
    'sequence',
    'home_team_id',
    'away_team_id',
    'home_score',
    'away_score',
    'winner_team_id',
    'loser_team_id',
    'played_at',
])]
class Game extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => GameStage::class,
            'sequence' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'played_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Championship, $this> */
    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function loser(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'loser_team_id');
    }
}
