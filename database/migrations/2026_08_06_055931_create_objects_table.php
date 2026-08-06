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
        // The central entity. Descriptive and SEO text move to
        // object_translations; placement (package, dates, pinned position)
        // moves to object_placements so a package change never rewrites this
        // row — both land in later migrations. territory_id points at the
        // most specific node (city, resort, or district); country_id is
        // denormalized on top of it for the same reason it is on
        // territories itself: scope queries filter by it on every request.
        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('object_type_id')->constrained();
            $table->foreignId('territory_id')->constrained();
            $table->foreignId('country_id')->constrained();

            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geography('geom', subtype: 'point', srid: 4326)->nullable();

            // The type-specific remainder, validated against the object
            // type's own declared attribute_schema — not a full EAV table,
            // which would turn the catalog query into a self-join over the
            // largest table in the schema.
            $table->jsonb('attributes')->nullable();

            // Publication state (what a visitor can see) and moderation
            // state (the latest decision on this object's current content)
            // are tracked separately: an object can be published while a
            // pending edit awaits review, since moderation acts on changes,
            // not on the live record.
            $table->enum('status', ['draft', 'published', 'hidden', 'archived'])->default('draft');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'revision_requested'])->nullable();

            // Availability is owner-asserted, not computed — no occupancy
            // calendar exists in the default configuration. Embedded as
            // columns on the object itself (the "current" state); every
            // transition is additionally recorded in availability_histories.
            // Default 'available': a newly activated object starts available.
            $table->enum('availability_status', ['available', 'unavailable', 'unspecified'])->default('available');
            $table->timestamp('availability_changed_at')->nullable();
            $table->foreignId('availability_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('availability_previous_status')->nullable();
            $table->timestamp('availability_last_confirmed_at')->nullable();
            $table->text('availability_comment')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objects');
    }
};
