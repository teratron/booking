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
        // A fixed, code-known set (ten types — placement expiring, package
        // expired, moderation outcomes, staleness, …), each raised from a
        // specific job or decision handler — unlike banner_slots or
        // article_categories, administrators do not invent new types
        // through the UI. No translations table: the type's back-office
        // label is Filament chrome (a translation key), not admin-editable
        // registry data; the actual per-language message text lives in
        // notification_templates.
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->enum('class', ['transactional', 'optional']);
            $table->jsonb('default_channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
