<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets — Equipment the organization owns and uses - assigned to people, depreciated, and reconciled against inventory.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets_records', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('tag')->nullable();
            $table->string('name');
            $table->string('category')->default('equipment');
            $table->string('serial_number')->nullable();
            $table->string('item_id')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('in_use');
            $table->string('condition')->default('good');
            $table->date('purchased_on')->nullable();
            $table->bigInteger('purchase_cost_minor')->default(0);
            $table->bigInteger('current_value_minor')->default(0);
            $table->integer('useful_life_years')->default(3);
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets_records');
    }
};
