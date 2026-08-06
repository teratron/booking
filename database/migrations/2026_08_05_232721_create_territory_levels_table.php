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
        // A fixed country -> region -> city ladder cannot represent Moldova,
        // Ukraine, and Georgia at once — level vocabularies are per-country,
        // administrator-editable data instead. depth_rank orders the
        // vocabulary for breadcrumb labelling; it does not constrain the
        // tree shape, which is territories.parent_id alone.
        Schema::create('territory_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('depth_rank');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territory_levels');
    }
};
