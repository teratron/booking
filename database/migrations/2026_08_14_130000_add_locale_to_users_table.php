<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gap `NotificationDispatchService::resolvePrimaryLocale()`'s own
     * docblock already named as deliberate and pending: no account-level
     * locale existed, so every recipient's notification rendered in the
     * portal's primary language regardless of who they were. A plain
     * string matching `languages.code`, not a foreign-key id — the same
     * convention astrotomic/laravel-translatable's own `locale` columns
     * already use throughout this schema. Null means "no preference set",
     * distinct from an empty string; the dispatch service falls back to
     * the portal's primary language exactly as it did before this column
     * existed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('country_id');
            $table->foreign('locale')->references('code')->on('languages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['locale']);
            $table->dropColumn('locale');
        });
    }
};
