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
        // The home page owns no domain data — every curated block is a
        // view onto entities another spec already owns (territories for
        // "popular destinations"/"popular cities", object types for
        // "object categories"), so this table stores selection and order
        // only, never a copy of the selected entity. Partners (an
        // advertising format) and the informational block (static
        // translated copy, not an entity selection) are deliberately
        // excluded — neither is a "which existing entities, in what order"
        // block. block_key is a fixed, code-known set tied to specific
        // home-page rendering code, the same reasoning that keeps
        // notification_types untranslated: administrators do not invent
        // new blocks through the UI. Curated selections are per country
        // from the start — retrofitting that dimension after a first
        // country launch would be a schema change no one wants.
        Schema::create('home_block_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('block_key');
            $table->morphs('selectable');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['country_id', 'block_key', 'selectable_type', 'selectable_id'],
                'home_block_selections_unique_entry'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_block_selections');
    }
};
