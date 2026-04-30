<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizer_id')->nullable()->after('event_id');
            $table->index(['organizer_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('ticket_used_at')->nullable()->after('ticket_issued_at');
            $table->unsignedBigInteger('ticket_used_by_organizer_id')->nullable()->after('ticket_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'ticket_used_at',
                'ticket_used_by_organizer_id',
            ]);
        });

        Schema::table('session_snapshots', function (Blueprint $table): void {
            $table->dropIndex(['organizer_id', 'status']);
            $table->dropColumn('organizer_id');
        });
    }
};
