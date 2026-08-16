<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filtered catalog views are not indexable by default — this table is
     * the allowlist that promotes exactly one, since a filter combination
     * of two or more is never promotable. `signature` is the canonical
     * `{filter_key}={filter_value}` string for the single active filter
     * (e.g. `type=3`, `territory=7`, `amenity=12`) — a plain string rather
     * than typed columns, since the catalog's filter dimensions are open
     * (object type, territory, amenity, price, rating, a type-declared
     * attribute) and a promotion is always a decision about one exact
     * value, never a range or a rule.
     */
    public function up(): void
    {
        Schema::create('catalog_filter_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('signature')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_filter_promotions');
    }
};
