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
        // A registry, not an enum, so adding an inventory position is a
        // data operation. surfaces is the closed set of page kinds the slot
        // may render on (home, country, region, city, resort, category,
        // object, news, article) — a tag list, not itself a foreign-keyed
        // entity, so it stores as a JSON array rather than a pivot table.
        Schema::create('banner_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('surfaces');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_slots');
    }
};
