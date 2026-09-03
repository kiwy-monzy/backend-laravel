<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The splash screen a site shows before its first paint.
 *
 * The React frontend had one — a `Preloader` that flashed the FGE wordmark
 * while the bundle downloaded — and it is the kind of thing a site owner wants
 * to choose rather than inherit. `splash_seconds` is the *maximum* it shows
 * for; it dismisses as soon as the page is ready, because a splash that
 * outlives the load is just a delay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('splash')->default('none');
            $table->unsignedSmallInteger('splash_seconds')->default(2);
            $table->string('splash_tagline')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['splash', 'splash_seconds', 'splash_tagline']);
        });
    }
};
