<?php

namespace App\Services;

use App\Models\SessionSeat;
use App\Models\SessionSnapshot;
use App\Models\SessionStandingArea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SessionSnapshotSynchronizer
{
    public function __construct(
        private readonly EventServiceClient $eventServiceClient,
        private readonly HallServiceClient $hallServiceClient,
    ) {
    }

    public function syncByEventSessionId(int $sessionId): SessionSnapshot
    {
        $existingSnapshot = SessionSnapshot::query()
            ->with(['seats', 'standingAreas'])
            ->where('event_session_id', $sessionId)
            ->first();

        if ($existingSnapshot !== null) {
            if ($existingSnapshot->organizer_id === null) {
                $session = $this->eventServiceClient->getSession($sessionId);

                if ($session !== null) {
                    $existingSnapshot->update([
                        'organizer_id' => data_get($session, 'event.organizer_id'),
                        'synced_at' => now(),
                    ]);
                }
            }

            return $existingSnapshot;
        }

        $session = $this->eventServiceClient->getSession($sessionId);

        if ($session === null) {
            throw (new ModelNotFoundException())->setModel(SessionSnapshot::class, [$sessionId]);
        }

        $hall = $this->hallServiceClient->getHall((int) $session['hall_id']);

        if ($hall === null) {
            throw (new ModelNotFoundException())->setModel(SessionSnapshot::class, [$sessionId]);
        }

        try {
            return DB::transaction(function () use ($sessionId, $session, $hall): SessionSnapshot {
                $snapshot = SessionSnapshot::query()
                    ->where('event_session_id', $sessionId)
                    ->lockForUpdate()
                    ->first();

                if ($snapshot !== null) {
                    return $snapshot->load(['seats', 'standingAreas']);
                }

                $snapshot = SessionSnapshot::query()->create([
                    'event_session_id' => $sessionId,
                    'event_id' => (int) $session['event_id'],
                    'organizer_id' => data_get($session, 'event.organizer_id'),
                    'hall_id' => (int) $session['hall_id'],
                    'event_title' => $session['event']['title'] ?? 'Untitled event',
                    'event_category_name' => $session['event']['category']['name'] ?? null,
                    'event_category_slug' => $session['event']['category']['slug'] ?? null,
                    'event_age_rating_label' => $session['event']['age_rating']['label'] ?? null,
                    'event_min_age' => (int) ($session['event']['age_rating']['min_age'] ?? 0),
                    'hall_name' => $hall['name'] ?? 'Hall',
                    'hall_address' => $hall['address'] ?? null,
                    'hall_layout' => $hall['layout'] ?? [],
                    'base_price' => $session['base_price'],
                    'currency' => config('booking.currency', 'RUB'),
                    'starts_at' => $session['start_time'],
                    'ends_at' => $session['end_time'],
                    'status' => $session['status'] ?? 'scheduled',
                    'synced_at' => now(),
                ]);

                $this->seedInventory($snapshot, $hall['layout']['elements'] ?? []);

                return $snapshot->load(['seats', 'standingAreas']);
            }, 3);
        } catch (QueryException $exception) {
            $existingSnapshot = SessionSnapshot::query()
                ->with(['seats', 'standingAreas'])
                ->where('event_session_id', $sessionId)
                ->first();

            if ($existingSnapshot !== null) {
                return $existingSnapshot;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, mixed>  $elements
     */
    private function seedInventory(SessionSnapshot $snapshot, array $elements): void
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $elementId = trim((string) ($element['id'] ?? ''));

            if ($elementId === '') {
                continue;
            }

            if (in_array($type, ['seat', 'vip_seat'], true)) {
                SessionSeat::query()->create([
                    'session_snapshot_id' => $snapshot->id,
                    'element_id' => $elementId,
                    'type' => $type,
                    'label' => $this->resolveSeatLabel($element),
                    'level_id' => $this->nullableString($element['level_id'] ?? null),
                    'row_label' => $this->nullableString($element['row'] ?? null),
                    'seat_number' => $this->nullableString($element['number'] ?? null),
                    'price' => $this->resolveElementPrice($type, (float) $snapshot->base_price),
                    'status' => SessionSeat::STATUS_FREE,
                ]);

                continue;
            }

            if (in_array($type, ['dancefloor', 'table'], true)) {
                $capacity = (int) ($element['capacity'] ?? ($type === 'table' ? 2 : 0));

                if ($capacity < 1) {
                    continue;
                }

                SessionStandingArea::query()->create([
                    'session_snapshot_id' => $snapshot->id,
                    'element_id' => $elementId,
                    'label' => $this->resolveStandingLabel($element, $type),
                    'level_id' => $this->nullableString($element['level_id'] ?? null),
                    'price' => $this->resolveElementPrice($type, (float) $snapshot->base_price),
                    'capacity_total' => $capacity,
                    'capacity_available' => $capacity,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function resolveSeatLabel(array $element): string
    {
        $label = trim((string) ($element['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        $row = trim((string) ($element['row'] ?? ''));
        $number = trim((string) ($element['number'] ?? ''));

        if ($row !== '' && $number !== '') {
            return 'Row ' . $row . ', seat ' . $number;
        }

        if ($row !== '') {
            return 'Row ' . $row;
        }

        if ($number !== '') {
            return 'Seat ' . $number;
        }

        return 'Seat';
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function resolveStandingLabel(array $element, string $type = 'dancefloor'): string
    {
        $label = trim((string) ($element['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        return $type === 'table' ? 'Table' : 'Dancefloor';
    }

    private function resolveElementPrice(string $type, float $basePrice): float
    {
        if ($type === 'vip_seat') {
            return round($basePrice * (float) config('booking.vip_price_multiplier', 1.5), 2);
        }

        return round($basePrice, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
