<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_unavailable_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hall_id');
            $table->dateTime('unavailable_start');
            $table->dateTime('unavailable_end');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('hall_id');
            $table->index(['hall_id', 'unavailable_start', 'unavailable_end'], 'hall_unavailable_periods_hall_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_unavailable_periods');
    }
};
