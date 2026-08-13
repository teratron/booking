<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enforces the dispatch pipeline's own idempotency guarantee at the
        // schema level: one channel gets at most one delivery-attempt record
        // per notification, so a pipeline bug that tries to queue a second
        // attempt fails loudly instead of silently duplicating a send.
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->unique(['notification_id', 'notification_channel_id'], 'notification_dispatches_unique_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->dropUnique('notification_dispatches_unique_channel');
        });
    }
};
