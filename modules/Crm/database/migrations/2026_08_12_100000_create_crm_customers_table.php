<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers, ported from `Contact` in knowlia-invoice/src/models.rs.
 *
 * One table rather than the Rust split of Contact / ContactPerson / Address:
 * a charity's customer record is one row with a billing address on it, and the
 * three-table shape only earns its keep when a customer has many sites. The
 * extra people are JSON on the row for the same reason — they are read with
 * the customer and never queried on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            // `customer` or `vendor` — the Rust model calls both a Contact,
            // and so do the invoice and bill screens that read this table.
            $table->string('contact_type')->default('customer');

            $table->string('display_name')->index();
            $table->string('company_name')->nullable();
            $table->string('salutation', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();

            $table->string('currency', 8)->default('TZS');
            $table->string('payment_terms')->default('due_on_receipt');
            $table->decimal('credit_limit', 16, 2)->default(0);
            $table->string('tax_number')->nullable();

            $table->string('billing_street')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postcode', 32)->nullable();
            $table->string('billing_country')->nullable();

            $table->json('contact_people')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
    }
};
