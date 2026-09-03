<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing — Purchase orders to vendors, and the bills that follow them.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('number');
            $table->string('vendor');
            $table->string('status')->default('draft');
            $table->date('ordered_on');
            $table->date('expected_on')->nullable();
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_orders');
    }
};
