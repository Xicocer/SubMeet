<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Tag;
use Illuminate\Support\Collection;

class EventTeaserService
{
    public const TAG_NAME = 'Тизер';
    public const TAG_SLUG = 'teaser';
    public const REASON = 'Событие уже опубликовано, но расписание и билеты появятся позже.';

    public function isReservedTagSlug(string $slug): bool
    {
        return $slug === self::TAG_SLUG;
    }

    public function isReservedTagName(string $name): bool
    {
        $normalizedName = mb_strtolower(trim($name));

        return in_array($normalizedName, ['тизер', self::TAG_SLUG], true);
    }

    public function hasAvailableSessions(Event $event): bool
    {
        if (array_key_exists('available_sessions_count', $event->getAttributes())) {
            return (int) $event->getAttribute('available_sessions_count') > 0;
        }

        return EventSession::query()
            ->where('event_id', $event->id)
            ->available()
            ->exists();
    }

    public function isTeaser(Event $event): bool
    {
        return $event->status === Event::STATUS_PUBLISHED && !$this->hasAvailableSessions($event);
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,system?:bool}>
     */
    public function transformTags(Event $event): array
    {
        $tags = $event->relationLoaded('tags')
            ? $event->tags
            : $event->tags()->get(['tags.id', 'tags.name', 'tags.slug']);

        $payload = $this->withoutTeaserTag($tags)
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]);

        if ($this->isTeaser($event)) {
            $systemTag = $this->tag();

            $payload->prepend([
                'id' => $systemTag->id,
                'name' => $systemTag->name,
                'slug' => $systemTag->slug,
                'system' => true,
            ]);
        }

        return $payload->values()->all();
    }

    public function sync(Event $event): void
    {
        $tag = $this->tag();

        if ($this->isTeaser($event)) {
            $event->tags()->syncWithoutDetaching([$tag->id]);

            return;
        }

        $event->tags()->detach($tag->id);
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @return Collection<int, Tag>
     */
    private function withoutTeaserTag(Collection $tags): Collection
    {
        return $tags->reject(fn (Tag $tag) => $this->isReservedTagSlug($tag->slug));
    }

    private function tag(): Tag
    {
        return Tag::query()->firstOrCreate(
            ['slug' => self::TAG_SLUG],
            ['name' => self::TAG_NAME],
        );
    }
}
