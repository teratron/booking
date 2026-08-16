<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which structured-data shape an object type's own pages emit —
     * `lodging`, `food`, or `place`. Declared per type, the same way
     * `has_rooms`/`has_availability_status` already are, so an
     * administrator adding a new object type classifies it without a code
     * change; nothing reads this by branching on a type's `key`. `place`
     * is the default for the backfill: schema.org's own most generic
     * location type, valid for any type this backfill cannot otherwise
     * identify as lodging or food.
     */
    public function up(): void
    {
        Schema::table('object_types', function (Blueprint $table): void {
            $table->string('structured_data_kind')->default('place');
        });

        DB::table('object_types')->whereIn('key', ['hotel', 'guesthouse', 'apartment', 'villa', 'sanatorium'])
            ->update(['structured_data_kind' => 'lodging']);

        DB::table('object_types')->whereIn('key', ['restaurant', 'cafe'])
            ->update(['structured_data_kind' => 'food']);
    }

    public function down(): void
    {
        Schema::table('object_types', function (Blueprint $table): void {
            $table->dropColumn('structured_data_kind');
        });
    }
};
