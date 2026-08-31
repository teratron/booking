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
        // Figma's own feedback-popup frame (node 244:230, "поп ап обратная
        // связь") carries a phone field alongside name and email — a
        // real, callable contact detail this portal's "hand off to the
        // owner/staff directly" model benefits from, not present when this
        // table was first created. Nullable: a visitor may still prefer
        // email-only contact.
        Schema::table('feedback_submissions', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_submissions', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
