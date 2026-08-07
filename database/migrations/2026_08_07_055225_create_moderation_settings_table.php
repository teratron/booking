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
     * The moderation mode must be selectable per country, per object
     * category, per owner, and per object — the same scoping ladder feature
     * modules use, most-specific-wins. The portal-wide default is
     * deliberately **not** a row here: it already exists as the
     * `moderation.default_mode` key in the typed settings registry, and
     * duplicating it as a fifth scope level would give the portal default
     * two independent sources of truth. This table therefore holds only the
     * four scopes that override it.
     *
     * `scope_reference_id` is never null, unlike `module_settings`'s column
     * of the same name — there is no portal row to need a nullable one for —
     * so a single composite unique constraint is sufficient here.
     */
    public function up(): void
    {
        Schema::create('moderation_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_level', ['country', 'category', 'owner', 'object']);
            $table->unsignedBigInteger('scope_reference_id');
            $table->enum('mode', ['immediate', 'review']);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('set_at');
            $table->timestamps();

            $table->unique(['scope_level', 'scope_reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_settings');
    }
};
