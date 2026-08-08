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
        // Denormalized off the polymorphic target at submission time —
        // `ScopedResource`'s scope narrowing filters on a direct column of
        // the resource's own table, and a country-scoped moderator's queue
        // is exactly that kind of filter. Both nullable: a target type that
        // carries no owner or country concept still submits a request.
        Schema::table('moderation_requests', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('target_id')->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->after('country_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderation_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
