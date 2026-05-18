<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_rental_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hall_id');
            $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->dateTime('requested_start');
            $table->dateTime('requested_end');
            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending');
            $table->text('organizer_message')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('hall_id');
            $table->index('organizer_id');
            $table->index('event_id');
            $table->index('status');
            $table->index(['hall_id', 'requested_start', 'requested_end'], 'hall_rental_requests_hall_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_rental_requests');
    }
};
