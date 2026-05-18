<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('customer_email')->nullable()->after('user_id');
            $table->string('guest_access_token', 96)->nullable()->unique()->after('customer_email');
            $table->decimal('subtotal_amount', 10, 2)->default(0)->after('flow_type');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal_amount');
            $table->unsignedInteger('loyalty_points_spent')->default(0)->after('discount_amount');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_points_spent');
            $table->dateTime('loyalty_points_awarded_at')->nullable()->after('loyalty_points_earned');
            $table->dateTime('ticket_sent_at')->nullable()->after('ticket_issued_at');

            $table->index(['customer_email', 'status']);
        });

        Schema::create('loyalty_point_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('balance')->default(0);
            $table->unsignedInteger('earned_total')->default(0);
            $table->unsignedInteger('spent_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_accounts');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['customer_email', 'status']);
            $table->dropUnique('bookings_guest_access_token_unique');
            $table->dropColumn([
                'customer_email',
                'guest_access_token',
                'subtotal_amount',
                'discount_amount',
                'loyalty_points_spent',
                'loyalty_points_earned',
                'loyalty_points_awarded_at',
                'ticket_sent_at',
            ]);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
