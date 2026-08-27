<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `RoleGrantService::revokeRole()` previously called
     * `$user->removeRole()` and left the matching `role_scopes` row
     * untouched — orphaned, with no record that it had ever been
     * withdrawn. The back-office specification requires a revocation to be
     * "recorded with actor and time", the same accountability pattern this
     * schema already applies to `blocked_by`/`blocked_at` on `users` and to
     * `deleted_by` elsewhere: a nullable actor column beside a nullable
     * timestamp, both null meaning "still in force".
     */
    public function up(): void
    {
        Schema::table('role_scopes', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('granted_at')->constrained('users')->cascadeOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_scopes', function (Blueprint $table) {
            $table->dropForeign(['revoked_by']);
            $table->dropColumn(['revoked_by', 'revoked_at']);
        });
    }
};
