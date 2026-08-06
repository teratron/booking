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
        // The current placement, one row per object — the append-only
        // history of every prior grant lives in placement_histories.
        // Pinning is a chief-administrator override of position only; it
        // must never change the tier a package's rank grants.
        Schema::create('object_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('placement_package_id')->constrained();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('pinned_position')->nullable();
            $table->integer('internal_priority')->default(0);
            $table->enum('expiry_action_override', ['demote', 'hide', 'review'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_placements');
    }
};
