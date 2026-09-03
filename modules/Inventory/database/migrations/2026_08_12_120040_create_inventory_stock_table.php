<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory — Stock on hand by location, with adjustments and batch tracking.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->string('location')->default('Main');
            $table->decimal('quantity', 16,3)->default(0);
            $table->decimal('reorder_level', 16,3)->default(0);
            $table->bigInteger('unit_cost_minor')->default(0);
            $table->string('batch')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock');
    }
};
