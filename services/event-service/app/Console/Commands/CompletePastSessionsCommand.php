<?php

namespace App\Console\Commands;

use App\Models\EventSession;
use Illuminate\Console\Command;

class CompletePastSessionsCommand extends Command
{
    protected $signature = 'sessions:complete-past {--chunk=200 : Number of past sessions to process per batch}';

    protected $description = 'Mark past scheduled sessions as completed';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $completed = 0;

        do {
            $sessionIds = EventSession::query()
                ->where('status', EventSession::STATUS_SCHEDULED)
                ->where('end_time', '<=', now())
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($sessionIds->isEmpty()) {
                break;
            }

            $completed += EventSession::query()
                ->whereIn('id', $sessionIds)
                ->update([
                    'status' => EventSession::STATUS_COMPLETED,
                    'updated_at' => now(),
                ]);
        } while ($sessionIds->count() === $chunk);

        $this->info("Completed {$completed} past session(s).");

        return self::SUCCESS;
    }
}
