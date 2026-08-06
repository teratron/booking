<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Language is data, not code — no language count or code is
        // hard-coded anywhere outside this table. `code` is the value
        // astrotomic/laravel-translatable's `locale` columns are expected to
        // match against; it is a plain string, not a foreign key, by that
        // package's own convention.
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('short_label', 10);
            $table->string('icon_path')->nullable();
            $table->enum('text_direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // "Exactly one" is a business rule (§5.1: "primary flag (exactly
        // one)"); a plain boolean column cannot express "at most one true"
        // on its own. Laravel's fluent Blueprint has no conditional-unique
        // primitive, so the partial index is raw SQL — a normal, accepted
        // escape hatch for a Postgres feature the schema builder does not
        // abstract.
        DB::statement(
            'create unique index languages_single_primary on languages (is_primary) where is_primary = true'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
