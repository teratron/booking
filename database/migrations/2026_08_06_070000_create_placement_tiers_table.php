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
        // Four ranks are structural — their count and relative order are not
        // administrator-editable, only their labels, badges, and colours are
        // (enforced at the application layer, not by a CHECK constraint,
        // since the rank count itself is a product decision, not a data
        // invariant this migration should hard-code).
        Schema::create('placement_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('rank')->unique();
            $table->string('border_colour');
            $table->string('badge_colour');
            $table->string('badge_icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_tiers');
    }
};
