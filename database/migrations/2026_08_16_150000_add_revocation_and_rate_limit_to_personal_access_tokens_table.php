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
        // `revoked_at`/`revoked_by` make revocation a recorded fact rather
        // than a deletion — an administrator reviewing a client's token
        // history needs to see that a token existed and was pulled, not find
        // a gap. `ApiToken::findToken()` filters on `revoked_at` so a
        // revoked token is rejected on its very next request rather than
        // surviving until it would otherwise have expired.
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();

            // Per-token override of the portal-wide default; null defers to
            // that default rather than duplicating its value here.
            $table->unsignedInteger('rate_limit_per_minute')->nullable()->after('revoked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'rate_limit_per_minute']);
        });
    }
};
