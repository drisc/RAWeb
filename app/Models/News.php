<?php

declare(strict_types=1);

namespace App\Models;

use App\Community\Concerns\HasAuthor;
use App\Community\Contracts\HasComments;
use App\Community\Enums\NewsCategory;
use App\Support\Database\Eloquent\BaseModel;
use Carbon\Carbon;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class News extends BaseModel implements HasComments, HasMedia
{
    use HasAuthor;
    /** @use HasFactory<NewsFactory> */
    use HasFactory;
    use InteractsWithMedia;

    use SoftDeletes;

    // TODO drop image_asset_path, migrate to media
    protected $table = 'news';

    protected $fillable = [
        'title',
        'lead',
        'body',
        'user_id',
        'link',
        'image_asset_path',
        'category',
        'publish_at',
        'unpublish_at',
        'pinned_at',
    ];

    protected $casts = [
        'category' => NewsCategory::class,
        'publish_at' => 'datetime',
        'unpublish_at' => 'datetime',
        'pinned_at' => 'datetime',
    ];

    // == media

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useFallbackUrl(asset('assets/images/news/image.webp'))
            // ->useFallbackPath(public_path('/assets/images/user/avatar.webp'))
            ->singleFile()
            // ->onlyKeepLatest(3)
            // ->acceptsFile(function (File $file) {
            //     return $file->mimeType === 'image/jpeg';
            // })
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('2xl')
                    ->nonQueued()
                    ->format('png')
                    ->fit(Fit::Max, 1000, 600);
            });
    }

    // == accessors

    public function getCanonicalUrlAttribute(): string
    {
        return route('news.show', [$this, $this->getSlugAttribute()]);
    }

    public function getIsPublishedAttribute(): bool
    {
        if ($this->publish_at && $this->unpublish_at) {
            return Carbon::now()->between($this->publish_at, $this->unpublish_at);
        }
        if ($this->publish_at) {
            return Carbon::now()->isAfter($this->publish_at);
        }

        return false;
    }

    public function getPermalinkAttribute(): string
    {
        return route('news.show', $this);
    }

    public function getSlugAttribute(): string
    {
        return $this->title ? '-' . Str::slug($this->title) : '';
    }

    // == mutators

    // == relations

    /**
     * @return MorphMany<NewsComment>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(NewsComment::class, 'commentable');
    }

    /**
     * @return BelongsTo<User, News>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    // == scopes
}
