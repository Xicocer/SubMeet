<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthUserEventPublisher;
use Illuminate\Console\Command;

class PublishAuthUsersSnapshotCommand extends Command
{
    protected $signature = 'rabbitmq:publish-auth-users {userId? : Optional auth user id}';

    protected $description = 'Publish current auth users to RabbitMQ for downstream projections';

    public function handle(AuthUserEventPublisher $publisher): int
    {
        $userId = $this->argument('userId');

        if ($userId !== null) {
            $user = User::query()->with('role')->findOrFail((int) $userId);
            $publisher->publishSynced($user);

            $this->info("Published auth user {$user->id} snapshot.");

            return self::SUCCESS;
        }

        $count = 0;

        User::query()
            ->with('role')
            ->orderBy('id')
            ->chunk(100, function ($users) use ($publisher, &$count) {
                foreach ($users as $user) {
                    $publisher->publishSynced($user);
                    $count++;
                }
            });

        $this->info("Published {$count} auth user snapshots.");

        return self::SUCCESS;
    }
}
