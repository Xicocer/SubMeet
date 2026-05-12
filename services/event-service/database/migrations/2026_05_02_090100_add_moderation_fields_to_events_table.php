<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('moderation_note')->nullable()->after('status');
            $table->timestamp('moderated_at')->nullable()->after('moderation_note');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'moderation_note',
                'moderated_at',
            ]);
        });
    }
};
