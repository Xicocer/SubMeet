<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_owner_id')->nullable()->after('organizer_id');
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('description');

            $table->index('venue_owner_id');
            $table->index('hourly_rate');
        });

        DB::table('halls')
            ->whereNull('venue_owner_id')
            ->update([
                'venue_owner_id' => DB::raw('organizer_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->dropIndex(['venue_owner_id']);
            $table->dropIndex(['hourly_rate']);
            $table->dropColumn(['venue_owner_id', 'hourly_rate']);
        });
    }
};
