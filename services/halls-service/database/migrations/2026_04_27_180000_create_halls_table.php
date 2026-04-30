<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->index('organizer_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('layout');
            $table->unsignedInteger('seat_capacity')->default(0);
            $table->unsignedInteger('vip_capacity')->default(0);
            $table->unsignedInteger('dancefloor_capacity')->default(0);
            $table->unsignedInteger('total_capacity')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halls');
    }
};
