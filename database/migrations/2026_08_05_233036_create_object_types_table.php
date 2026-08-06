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
        // The type registry is administrator-managed data, not code — adding
        // "glamping" is a data operation ([TZ] §69, §109). The application
        // must never branch on `key`; it exists as a stable, URL-facing
        // identifier (the /catalog/{type} route segment), not a code switch.
        // Filterable, per-type fields are typed columns elsewhere; the
        // type-specific remainder is validated against attribute_schema —
        // the JSONB bag on objects (built in a later phase) is checked
        // against whatever this column declares, so a new type never needs a
        // schema migration.
        Schema::create('object_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('object_types')->nullOnDelete();
            $table->string('key')->unique();
            $table->string('icon_path')->nullable();
            $table->jsonb('attribute_schema')->nullable();

            // Structural capability flags: whether the type participates in
            // relations that are their own tables (rooms/prices, the
            // availability badge), not fields that live in attribute_schema.
            $table->boolean('has_rooms')->default(false);
            $table->boolean('has_availability_status')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_types');
    }
};
