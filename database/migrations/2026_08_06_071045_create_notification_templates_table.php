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
        // One template per (type, language, channel) — an email template
        // needs a subject line, an inbox entry does not, hence subject is
        // nullable rather than a column that only some rows use meaningfully.
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_type_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->foreignId('notification_channel_id')->constrained();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('languages')->cascadeOnDelete();
            $table->unique(
                ['notification_type_id', 'locale', 'notification_channel_id'],
                'notification_templates_unique_variant'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
