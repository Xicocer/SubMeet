<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_session_id')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('hall_id');
            $table->string('event_title');
            $table->string('event_category_name')->nullable();
            $table->string('event_category_slug')->nullable();
            $table->string('event_age_rating_label')->nullable();
            $table->unsignedInteger('event_min_age')->default(0);
            $table->string('hall_name');
            $table->json('hall_layout');
            $table->decimal('base_price', 10, 2);
            $table->string('currency', 3)->default('RUB');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 50)->default('scheduled');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['hall_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_snapshots');
    }
};
