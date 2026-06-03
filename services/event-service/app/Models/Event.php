<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class Event extends Model
{
    use HasFactory;
    use Searchable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title',
        'description',
        'poster_url',
        'category_id',
        'age_rating_id',
        'organizer_id',
        'status',
        'moderation_note',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'age_rating_id' => 'integer',
            'organizer_id' => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ageRating(): BelongsTo
    {
        return $this->belongsTo(AgeRating::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'organizer_id', 'auth_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(EventFavorite::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $category = $this->relationLoaded('category')
            ? $this->category
            : $this->category()->first(['id', 'name', 'slug']);

        $ageRating = $this->relationLoaded('ageRating')
            ? $this->ageRating
            : $this->ageRating()->first(['id', 'label', 'min_age']);

        $tags = $this->relationLoaded('tags')
            ? $this->tags
            : $this->tags()->get(['tags.id', 'tags.name', 'tags.slug']);

        return [
            'id' => $this->id,
            'title' => (string) $this->title,
            'description' => (string) $this->description,
            'category_name' => (string) ($category?->name ?? ''),
            'category_slug' => (string) ($category?->slug ?? ''),
            'age_label' => (string) ($ageRating?->label ?? ''),
            'age_min' => (int) ($ageRating?->min_age ?? 0),
            'tag_names' => $tags instanceof Collection
                ? $tags->pluck('name')->filter()->values()->all()
                : [],
            'tag_slugs' => $tags instanceof Collection
                ? $tags->pluck('slug')->filter()->values()->all()
                : [],
            'organizer_id' => (int) $this->organizer_id,
            'status' => (string) $this->status,
            'created_at' => $this->created_at?->timestamp ?? 0,
        ];
    }
}
