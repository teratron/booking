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
        // A package binds a tier to a price and a validity period; the
        // object-type scope is optional because some packages are sold
        // across every category and others are specific to hotels,
        // restaurants, sanatoria, or holiday bases.
        Schema::create('placement_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_tier_id')->constrained();
            $table->foreignId('object_type_id')->nullable()->constrained();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->unsignedInteger('validity_days');

            // Bump availability and frequency are the single package-varying
            // capability beyond tier and decoration — every other
            // entitlement (photos, contacts, description length) is
            // identical across packages.
            $table->boolean('bump_allowed')->default(false);
            $table->unsignedInteger('bump_interval_hours')->nullable();
            $table->unsignedInteger('free_bumps_per_period')->nullable();
            $table->decimal('paid_bump_price', 10, 2)->nullable();

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
        Schema::dropIfExists('placement_packages');
    }
};
