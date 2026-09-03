<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses — Money going out: expense claims against accounts, with receipts and approval.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses_records', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('reference')->nullable();
            $table->string('account');
            $table->string('vendor')->nullable();
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->date('spent_on');
            $table->string('status')->default('draft');
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->string('receipt_url', 500)->nullable();
            $table->boolean('billable')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses_records');
    }
};
