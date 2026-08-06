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
        // Resolution is most-specific-wins down the ladder (object → owner
        // → category → country → portal → registry default). scope_reference
        // is a soft reference, deliberately: the target table depends on
        // scope_level (countries, object_types, users, or objects; null for
        // portal), so a single hard foreign key cannot express it — the
        // same pattern role_scopes established for the permission ladder.
        // Two indexes rather than one composite unique, because Postgres
        // never treats two NULLs as equal: a plain unique(module_id,
        // scope_level, scope_reference_id) would let the same module be
        // set at portal scope more than once, since every portal-scope row
        // has a null reference.
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_level', ['portal', 'country', 'category', 'owner', 'object']);
            $table->unsignedBigInteger('scope_reference_id')->nullable();
            $table->enum('state', ['enabled', 'disabled']);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('set_at');
            $table->timestamps();

            $table->index(['scope_level', 'scope_reference_id']);
        });

        DB::statement(
            'create unique index module_settings_unique_portal on module_settings (module_id) '.
            "where scope_level = 'portal'"
        );
        DB::statement(
            'create unique index module_settings_unique_scoped on module_settings '.
            '(module_id, scope_level, scope_reference_id) '.
            "where scope_level <> 'portal'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_settings');
    }
};
