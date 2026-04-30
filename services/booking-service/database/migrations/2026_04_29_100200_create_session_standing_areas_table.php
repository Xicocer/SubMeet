<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_standing_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_snapshot_id')->constrained('session_snapshots')->cascadeOnDelete();
            $table->string('element_id');
            $table->string('label');
            $table->string('level_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('capacity_total');
            $table->unsignedInteger('capacity_available');
            $table->timestamps();

            $table->unique(['session_snapshot_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_standing_areas');
    }
};
