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
     * A territory's full slug path is derived from its ancestors' own
     * per-language slugs, and deriving it on every URL resolution would make
     * every territory page a recursive walk — the same cost the descendant
     * expansion at the object-listing layer already exists to avoid. Cached
     * here per language, invalidated on reparent or on any slug edit to the
     * territory or an ancestor.
     */
    public function up(): void
    {
        Schema::table('territory_translations', function (Blueprint $table) {
            $table->string('full_slug_path')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_translations', function (Blueprint $table) {
            $table->dropColumn('full_slug_path');
        });
    }
};
