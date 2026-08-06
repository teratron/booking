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
        // Administrator-defined card decoration, granted to an object
        // independently of its placement package — an unlimited,
        // back-office-configured set (VIP, Recommended, Popular, Verified,
        // Tourist's Choice, New, Promotion, …), never a code enum.
        Schema::create('promotion_labels', function (Blueprint $table) {
            $table->id();
            $table->string('border_colour');
            $table->string('text_colour');
            $table->string('background_colour');
            $table->string('icon')->nullable();
            $table->string('position_on_card');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_labels');
    }
};
