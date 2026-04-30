<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_snapshots', function (Blueprint $table) {
            $table->string('hall_address')->nullable()->after('hall_name');
        });
    }

    public function down(): void
    {
        Schema::table('session_snapshots', function (Blueprint $table) {
            $table->dropColumn('hall_address');
        });
    }
};
