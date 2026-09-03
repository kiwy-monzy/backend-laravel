<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product classification on each item.
 *
 * `google_category` holds a Google product-taxonomy id — the same code a
 * marketplace feed or a customs declaration expects, so an item classified
 * once here does not have to be re-classified downstream.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_items', function (Blueprint $table) {
            $table->string('google_category')->nullable()->after('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_items', fn (Blueprint $t) => $t->dropColumn('google_category'));
    }
};
