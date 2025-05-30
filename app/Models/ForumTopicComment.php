<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permissions;
use App\Support\Database\Eloquent\BaseModel;
use Database\Factories\ForumTopicCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumTopicComment extends BaseModel
{
    /** @use HasFactory<ForumTopicCommentFactory> */
    use HasFactory;
    use SoftDeletes;

    // TODO drop is_authorized, migrate to authorized_at
    protected $table = 'forum_topic_comments';

    protected $fillable = [
        'forum_topic_id',
        'body',
        'author_id',
        'is_authorized',
        'authorized_at',
    ];

    protected $casts = [
        'authorized_at' => 'datetime',
        'is_authorized' => 'boolean',
    ];

    protected static function newFactory(): ForumTopicCommentFactory
    {
        return ForumTopicCommentFactory::new();
    }

    // == accessors

    public function getEditLinkAttribute(): string
    {
        return route('forum-topic-comment.edit', $this);
    }

    public function getIsAuthorizedAttribute(): bool
    {
        return $this->authorized_at?->isPast() || (bool) $this->attributes['is_authorized'];
    }

    public function getPermalinkAttribute(): string
    {
        return route('forum-topic-comment.show', $this);
    }

    // == relations

    /**
     * @return BelongsTo<ForumTopic, ForumTopicComment>
     */
    public function forumTopic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /**
     * @return BelongsTo<User, ForumTopicComment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    // == scopes

    /**
     * @param Builder<ForumTopicComment> $query
     * @return Builder<ForumTopicComment>
     */
    public function scopeAuthorized(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->where('is_authorized', 1)
                ->orWhereNotNull('authorized_at');
        });
    }

    /**
     * @param Builder<ForumTopicComment> $query
     * @return Builder<ForumTopicComment>
     */
    public function scopeUnauthorized(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->where('is_authorized', 0)
                ->orWhereNull('authorized_at');
        });
    }

    /**
     * @param Builder<ForumTopicComment> $query
     * @return Builder<ForumTopicComment>
     */
    public function scopeViewable(Builder $query, ?User $user = null): Builder
    {
        $userPermissions = $user ? (int) $user->getAttribute('Permissions') : Permissions::Unregistered;

        return $query->whereHas('forumTopic', function ($query) use ($userPermissions) {
            $query->where('required_permissions', '<=', $userPermissions);
        });
    }

    /**
     * @param Builder<ForumTopicComment> $query
     * @return Builder<ForumTopicComment>
     */
    public function scopeVisibleTo(Builder $query, ?User $currentUser = null): Builder
    {
        return $query->where(function ($query) use ($currentUser) {
            // Anyone can see authorized comments.
            $query->where('is_authorized', true);

            if ($currentUser) {
                // Users can always see their own comments.
                $query->orWhere('author_id', $currentUser->id);

                // Some users can see all comments.
                if ($currentUser->hasAnyRole([
                    Role::ADMINISTRATOR,
                    Role::MODERATOR,
                    Role::FORUM_MANAGER,
                ])) {
                    $query->orWhereRaw('1=1');
                }
            }
        });
    }
}
