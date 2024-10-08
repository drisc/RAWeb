<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database\Eloquent\BasePivot;
use Database\Factories\PlayerAchievementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAchievement extends BasePivot
{
    /** @use HasFactory<PlayerAchievementFactory> */
    use HasFactory;

    protected $table = 'player_achievements';

    public const CREATED_AT = 'unlocked_at';
    public const UPDATED_AT = null;

    protected static function newFactory(): PlayerAchievementFactory
    {
        return PlayerAchievementFactory::new();
    }

    protected $fillable = [
        'user_id',
        'achievement_id',
        'trigger_id',
        'player_session_id',
        'unlocked_at',
        'unlocked_hardcore_at',
        'unlocker_id',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'unlocked_hardcore_at' => 'datetime',
    ];

    // == accessors

    // == mutators

    // == relations

    /**
     * @return BelongsTo<Achievement, PlayerAchievement>
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class, 'achievement_id');
    }

    /**
     * @return BelongsTo<User, PlayerAchievement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    /**
     * @return BelongsTo<User, PlayerAchievement>
     */
    public function player(): BelongsTo
    {
        return $this->player();
    }

    // == scopes

    /**
     * @param Builder<PlayerAchievement> $query
     * @return Builder<PlayerAchievement>
     */
    public function scopeForGame(Builder $query, Game $game): Builder
    {
        return $query->whereHas('achievement', function ($query) use ($game) {
            $query->where('GameID', $game->id);
        });
    }
}
