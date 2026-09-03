<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders — Sales orders before they become invoices - what a customer asked for and what has been fulfilled.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('number');
            $table->string('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('channel')->default('in_person');
            $table->string('status')->default('draft');
            $table->date('ordered_on');
            $table->date('required_on')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->string('document_id')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_orders');
    }
};
