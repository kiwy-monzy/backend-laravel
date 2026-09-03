<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last eight tables that stored names instead of keys.
 *
 * Every one of these had a counterparty written as free text — a purchase order
 * whose "vendor" was a string, a project whose "customer" was a string — so the
 * obvious questions could not be asked: what have we bought from this supplier,
 * what does this customer's work total, which department is over its budget.
 * Two spellings of one company were two companies.
 *
 * The text columns stay and are still what the lists print; the keys are what
 * make them joinable. Backfilling matches on the name, which is exactly as
 * reliable as the names were — and is why the keys exist from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchasing_orders', function (Blueprint $table) {
            $table->string('vendor_id')->nullable()->index();
        });

        Schema::table('projects_records', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->index();
            $table->string('contract_id')->nullable()->index();
            $table->string('department_id')->nullable()->index();
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->index();
        });

        Schema::table('bookings_appointments', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->index();
            // The seat holding the appointment: the team is the staff list.
            $table->unsignedBigInteger('staff_member_id')->nullable()->index();
            $table->string('service_id')->nullable()->index();
        });

        Schema::table('fulfillment_shipments', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->index();
            $table->string('order_id')->nullable()->index();
        });

        Schema::table('expenses_records', function (Blueprint $table) {
            $table->string('vendor_id')->nullable()->index();
            $table->string('account_id')->nullable()->index();
            $table->string('department_id')->nullable()->index();
            // An expense incurred on a job belongs to that job's cost.
            $table->string('contract_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->string('department_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_id')->nullable()->index();
            // A request becomes an order; keeping the link is what lets anyone
            // ask whether what was asked for was ever bought.
            $table->string('purchase_order_id')->nullable()->index();
        });

        Schema::table('cart_orders', function (Blueprint $table) {
            $table->string('shipment_id')->nullable()->index();
        });

        // Where a journal entry came from, so the books can be traced back to
        // the invoice, expense or certificate that caused them.
        Schema::table('accounting_journal_entries', function (Blueprint $table) {
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchasing_orders', fn (Blueprint $t) => $t->dropColumn('vendor_id'));
        Schema::table('projects_records', fn (Blueprint $t) => $t->dropColumn(['customer_id', 'contract_id', 'department_id']));
        Schema::table('billing_subscriptions', fn (Blueprint $t) => $t->dropColumn('customer_id'));
        Schema::table('bookings_appointments', fn (Blueprint $t) => $t->dropColumn(['customer_id', 'staff_member_id', 'service_id']));
        Schema::table('fulfillment_shipments', fn (Blueprint $t) => $t->dropColumn(['customer_id', 'order_id']));
        Schema::table('expenses_records', fn (Blueprint $t) => $t->dropColumn(['vendor_id', 'account_id', 'department_id', 'contract_id', 'project_id']));
        Schema::table('procurement_requests', fn (Blueprint $t) => $t->dropColumn(['department_id', 'requested_by_id', 'purchase_order_id']));
        Schema::table('cart_orders', fn (Blueprint $t) => $t->dropColumn('shipment_id'));
        Schema::table('accounting_journal_entries', fn (Blueprint $t) => $t->dropColumn(['source_type', 'source_id']));
    }
};
