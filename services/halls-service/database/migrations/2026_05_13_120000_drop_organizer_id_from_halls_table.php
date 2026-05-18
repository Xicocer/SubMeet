<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('halls', 'organizer_id')) {
            return;
        }

        Schema::table('halls', function (Blueprint $table) {
            $table->dropIndex(['organizer_id']);
            $table->dropColumn('organizer_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('halls', 'organizer_id')) {
            return;
        }

        Schema::table('halls', function (Blueprint $table) {
            $table->unsignedBigInteger('organizer_id')->nullable()->after('id');
            $table->index('organizer_id');
        });
    }
};
