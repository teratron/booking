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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('flag_path')->nullable();
            $table->string('currency', 3);
            $table->string('phone_code', 10);

            // No launch country's own primary language is active at
            // release — Moldova/Ukraine/Georgia's primary languages are
            // Romanian/Ukrainian/Georgian, none of which ship active.
            // This FK is deliberately unconstrained by is_active: an
            // inactive primary language is a normal, resolvable state, not
            // a validation error.
            $table->foreignId('primary_language_id')->constrained('languages');

            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
