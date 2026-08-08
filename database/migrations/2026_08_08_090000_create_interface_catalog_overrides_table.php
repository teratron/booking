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
        // Overlays administrator edits on top of the file-based interface
        // catalogs under resources/lang — a row here always wins over the
        // shipped file value. Not an entity-translation sibling table (no
        // "interface" entity it translates) so it is named apart from the
        // `{entity}_translations` convention those tables follow. `locale`
        // is a plain string matching `languages.code`, the same convention
        // astrotomic/laravel-translatable uses elsewhere in this schema, not
        // a foreign key. `group` is the catalog file name (e.g. "panel");
        // `key` is the dot-notation path inside it (e.g. "objects.columns.name").
        Schema::create('interface_catalog_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            $table->string('group', 60);
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['locale', 'group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interface_catalog_overrides');
    }
};
