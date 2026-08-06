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
        // Modelling the *type* as a registry rather than an enum is what
        // makes [TZ] §74's "other social networks" and §64's channel
        // extensibility true without a migration — adding a channel is one
        // data row. link_template is how a raw value (a phone number) becomes
        // an actionable deep link (e.g. "https://wa.me/{value}") without the
        // owner ever entering a URI themselves.
        Schema::create('contact_channel_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('icon_path')->nullable();
            $table->string('link_template')->nullable();
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
        Schema::dropIfExists('contact_channel_types');
    }
};
