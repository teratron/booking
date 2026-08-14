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
        Schema::table('territories', function (Blueprint $table) {
            // Editorial curation for the home page's "popular destinations"
            // (top-level, is_featured) and "popular cities" (non-top-level,
            // is_featured) blocks — depth (parent_id) distinguishes the two
            // without ever branching on a hard-coded level name.
            $table->boolean('is_featured')->default(false)->after('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territories', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
