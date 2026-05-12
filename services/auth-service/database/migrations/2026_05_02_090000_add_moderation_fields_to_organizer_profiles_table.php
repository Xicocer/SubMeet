<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table) {
            $table->string('moderation_status', 30)
                ->default('approved')
                ->after('company_name');
            $table->text('moderation_note')->nullable()->after('moderation_status');
            $table->timestamp('moderated_at')->nullable()->after('moderation_note');
        });

        DB::table('organizer_profiles')
            ->whereNull('moderation_status')
            ->update([
                'moderation_status' => 'approved',
            ]);
    }

    public function down(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'moderation_status',
                'moderation_note',
                'moderated_at',
            ]);
        });
    }
};
