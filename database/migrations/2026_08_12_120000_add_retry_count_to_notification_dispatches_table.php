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
        // Tracks the scheduled retry sweep's own attempt budget, kept
        // separate from DispatchNotificationJob's queue-level `$tries`: that
        // limit governs one delivery attempt cycle (seconds of backoff),
        // while this one governs how many further cycles a dispatch that
        // still ended up `failed` gets from the sweep (routes/console.php)
        // before it is left `failed` permanently for an administrator to see.
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_count')->default(0)->after('provider_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });
    }
};
