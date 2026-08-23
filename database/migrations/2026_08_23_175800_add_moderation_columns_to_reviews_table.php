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
        // Denormalized from the owning object at submission time, the same
        // pattern moderation_requests already uses for its own polymorphic
        // target — a review carries no country/territory/category column of
        // its own, and ScopedResource's scope-narrowing query needs a plain
        // column on this table, not a join through `object_id`, to keep a
        // country-scoped moderator's review queue bounded the same way
        // every other scoped admin list already is.
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('object_id')->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('object_type_id')->nullable()->after('territory_id')->constrained()->nullOnDelete();
            // Distinct from hidden_reason (an upheld-report removal, via
            // soft delete) — this is why a moderator declined to publish a
            // review that was never live in the first place.
            $table->text('rejection_reason')->nullable()->after('hidden_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('territory_id');
            $table->dropConstrainedForeignId('object_type_id');
            $table->dropColumn('rejection_reason');
        });
    }
};
