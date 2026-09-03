<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the five public layouts a site renders in, and with what palette.
 *
 * `theme_overrides` holds the handful of CSS custom properties an owner has
 * changed by hand. Keeping them apart from `theme` means switching preset
 * palettes does not silently discard the tweaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('template')->default('template1');
            $table->string('theme')->default('fge');
            $table->json('theme_overrides')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['template', 'theme', 'theme_overrides']);
        });
    }
};
