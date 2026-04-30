<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_snapshot_id')->constrained('session_snapshots')->cascadeOnDelete();
            $table->string('element_id');
            $table->string('type', 50);
            $table->string('label');
            $table->string('level_id')->nullable();
            $table->string('row_label')->nullable();
            $table->string('seat_number')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('status', 50)->default('free');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->dateTime('reserved_until')->nullable();
            $table->timestamps();

            $table->unique(['session_snapshot_id', 'element_id']);
            $table->index(['session_snapshot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_seats');
    }
};
