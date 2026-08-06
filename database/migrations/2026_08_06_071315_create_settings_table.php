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
        // Portal-wide settings — email templates, analytics integration
        // toggles, the home page's informational-block copy, and every
        // other administrator-editable value that is not its own entity.
        // A single JSONB value column covers strings, numbers, structured
        // config, and per-locale translated text uniformly, avoiding a
        // dedicated table per setting kind.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
