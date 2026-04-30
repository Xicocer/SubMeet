<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('flow_type', 30)->default('reservation')->after('status');
            $table->string('ticket_code')->nullable()->unique()->after('currency');
            $table->string('ticket_pdf_path')->nullable()->after('ticket_code');
            $table->dateTime('ticket_issued_at')->nullable()->after('confirmed_at');
        });

        DB::table('bookings')
            ->where('status', 'pending_payment')
            ->update([
                'status' => 'reserved',
                'flow_type' => 'reservation',
            ]);

        Schema::table('payments', function (Blueprint $table): void {
            $table->text('confirmation_url')->nullable()->after('external_reference');
            $table->string('failure_reason')->nullable()->after('confirmation_url');
            $table->dateTime('cancelled_at')->nullable()->after('paid_at');
            $table->dateTime('last_synced_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'confirmation_url',
                'failure_reason',
                'cancelled_at',
                'last_synced_at',
            ]);
        });

        DB::table('bookings')
            ->where('status', 'reserved')
            ->update([
                'status' => 'pending_payment',
            ]);

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique('bookings_ticket_code_unique');
            $table->dropColumn([
                'flow_type',
                'ticket_code',
                'ticket_pdf_path',
                'ticket_issued_at',
            ]);
        });
    }
};
