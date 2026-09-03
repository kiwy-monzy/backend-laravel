<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items, documents and their lines — ported from `Item`, `Document`,
 * `LineItem` and `Payment` in knowlia-invoice/src/models.rs.
 *
 * **Money is stored in minor units as integers.** `decimal` in SQLite is
 * really a float, and a float is the wrong type for money: 0.1 + 0.2 is not
 * 0.3, and an invoice that adds up to a cent out is an invoice a customer
 * disputes. Everything here is TZS cents (or the org currency's minor unit),
 * converted at the edges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('name')->index();
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            // `goods` or `service` — a service has no stock to track.
            $table->string('item_type')->default('service');
            $table->string('unit')->default('unit');

            $table->bigInteger('rate_minor')->default(0);
            $table->bigInteger('purchase_rate_minor')->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0);

            $table->boolean('track_inventory')->default(false);
            $table->decimal('stock_on_hand', 16, 3)->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoicing_documents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('customer_id')->nullable()->index();

            // invoice | estimate | credit_note | bill
            $table->string('doc_type')->default('invoice');
            $table->string('number')->index();
            // draft | sent | partially_paid | paid | overdue | void
            $table->string('status')->default('draft');

            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->string('currency', 8)->default('TZS');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'doc_type', 'number']);
        });

        Schema::create('invoicing_lines', function (Blueprint $table) {
            $table->id();
            $table->string('document_id')->index();
            $table->string('item_id')->nullable();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 16, 3)->default(1);
            $table->bigInteger('rate_minor')->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->integer('position')->default(0);

            $table->timestamps();
        });

        Schema::create('invoicing_payments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('document_id')->index();
            $table->string('customer_id')->nullable()->index();

            $table->bigInteger('amount_minor');
            $table->date('paid_on');
            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_payments');
        Schema::dropIfExists('invoicing_lines');
        Schema::dropIfExists('invoicing_documents');
        Schema::dropIfExists('invoicing_items');
    }
};
