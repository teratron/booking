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
        // Groups amenities for owner-facing selection (general, grounds,
        // catering, rooms, pool and SPA, family, business, transport,
        // accessibility, pets — [TZ] §36, §78, §110). The group's own name is
        // translated the same way as everything else content-bearing; this
        // table carries only the invariant fields.
        Schema::create('amenity_groups', function (Blueprint $table) {
            $table->id();
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
        Schema::dropIfExists('amenity_groups');
    }
};
