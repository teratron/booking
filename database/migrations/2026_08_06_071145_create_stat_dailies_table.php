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
        // The aggregate tier — unbounded history at bounded cost. A
        // scheduled rollup compacts stat_events into this table and
        // discards the rolled-up raw rows past retention; this migration
        // only shapes the columns. Uniqueness matches the source
        // specification's own tuple exactly (date, subject, kind, channel,
        // territory, language) — country is a stored, denormalized column
        // but deliberately not part of the key, since it is redundant with
        // territory for de-duplication purposes.
        Schema::create('stat_dailies', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->morphs('subject');
            $table->enum('kind', [
                'object_card_view', 'object_page_view', 'photo_view',
                'contact_click', 'banner_impression', 'banner_click',
            ]);
            $table->foreignId('contact_channel_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 10)->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->nullOnDelete();
            $table->unique(
                ['date', 'subject_type', 'subject_id', 'kind', 'contact_channel_type_id', 'territory_id', 'locale'],
                'stat_dailies_unique_rollup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stat_dailies');
    }
};
