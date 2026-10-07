<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `reminder_stage` records the furthest payment reminder already sent for
     * this period, so the hourly sweep never sends the same one twice. Each
     * renewal is a fresh row, so the next period starts with a clean slate.
     *
     * `paused_at` sits beside `cancelled_at`: a pause is the sweep's verdict on
     * a period nobody paid for, not a customer choosing to leave.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('reminder_stage')->nullable()->after('cancelled_at');
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_stage');
            $table->dateTime('paused_at')->nullable()->after('reminder_sent_at');

            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'ends_at']);

            $table->dropColumn(['reminder_stage', 'reminder_sent_at', 'paused_at']);
        });
    }
};
