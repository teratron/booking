<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `position_on_card` shipped as an unconstrained varchar despite the
        // model casting it to CardPosition — a value outside the enum's four
        // cases reaches the row fine on write, then throws ValueError the
        // moment any query touches it, taking down the whole list page with
        // no way to open the offending record to fix it. The constraint
        // closes the actual gap; the enum cast alone was never enforcement.
        DB::statement(<<<'SQL'
            alter table promotion_labels add constraint promotion_labels_position_on_card_check
            check (position_on_card in ('top-left', 'top-right', 'bottom-left', 'bottom-right'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table promotion_labels drop constraint promotion_labels_position_on_card_check');
    }
};
