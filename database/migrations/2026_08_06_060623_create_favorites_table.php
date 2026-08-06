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
        // Visitor-facing, not an owner-side bookmark — an owner already
        // reaches their own objects through the object switcher, so a
        // favorite count is a genuine demand signal instead. The portal has
        // no visitor accounts in its default configuration, so a favorite
        // is keyed by either a signed-in user or an anonymous browser
        // token, never both and never neither.
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('browser_token')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(
            'alter table favorites add constraint favorites_exactly_one_identity check '.
            '((user_id is not null and browser_token is null) or (user_id is null and browser_token is not null))'
        );

        // Two partial unique indexes rather than one composite: a plain
        // unique(object_id, user_id) would not stop duplicate anonymous
        // favorites, since Postgres never treats two NULLs as equal.
        DB::statement(
            'create unique index favorites_unique_user on favorites (object_id, user_id) where user_id is not null'
        );
        DB::statement(
            'create unique index favorites_unique_browser_token on favorites (object_id, browser_token) where browser_token is not null'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
