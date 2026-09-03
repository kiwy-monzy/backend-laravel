<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing — Recurring subscriptions sold to your own customers.
 *
 * Ported from the matching model in crates/knowlia-invoice/src/models.rs.
 * Money is stored in minor units as integers: `decimal` in SQLite is a float,
 * and a float is the wrong type for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('customer');
            $table->string('plan_name');
            $table->string('status')->default('active');
            $table->string('interval')->default('monthly');
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('TZS');
            $table->date('started_on');
            $table->date('next_charge_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
