<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the aggregate pages actually need.
 *
 * Found by loading four hundred thousand rows and timing every page rather than
 * by guessing: the customer ledger groups documents by customer, and the
 * invoicing overview sums them by status. Both were full scans with a temporary
 * b-tree on top, which is invisible on a demo database and two and a half
 * seconds on a real one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_documents', function (Blueprint $table) {
            // The customer ledger's GROUP BY: scanning in customer order lets
            // SQLite group without sorting the whole table first.
            $table->index(['organization_id', 'customer_id'], 'doc_org_customer');
            // The overview's outstanding total, which filters on status.
            $table->index(['organization_id', 'status'], 'doc_org_status');
        });

        Schema::table('invoicing_lines', function (Blueprint $table) {
            $table->index('document_id', 'lines_document');
        });

        Schema::table('accounting_journal_lines', function (Blueprint $table) {
            $table->index(['account_id', 'entry_id'], 'jl_account_entry');
        });

        Schema::table('inventory_stock', function (Blueprint $table) {
            $table->index(['organization_id', 'item_id'], 'stock_org_item');
        });

        Schema::table('assets_records', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'asset_org_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_documents', function (Blueprint $table) {
            $table->dropIndex('doc_org_customer');
            $table->dropIndex('doc_org_status');
        });
        Schema::table('invoicing_lines', fn (Blueprint $t) => $t->dropIndex('lines_document'));
        Schema::table('accounting_journal_lines', fn (Blueprint $t) => $t->dropIndex('jl_account_entry'));
        Schema::table('inventory_stock', fn (Blueprint $t) => $t->dropIndex('stock_org_item'));
        Schema::table('assets_records', fn (Blueprint $t) => $t->dropIndex('asset_org_status'));
    }
};
