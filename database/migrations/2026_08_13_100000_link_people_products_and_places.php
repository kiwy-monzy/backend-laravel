<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the free-text joins into real ones.
 *
 * Three things were strings pretending to be relationships, and each one meant
 * a question the system could not answer:
 *
 *   • `organization_members` had no department, so "who is in Finance" had no
 *     answer and a department was a name with a budget and nobody in it.
 *   • `inventory_stock` held `item_name` and `sku` as text, so the same product
 *     could be stocked under two spellings and never reconcile with the item it
 *     is sold from.
 *   • `assets_records.department` and `.assigned_to` were text, so an asset
 *     could be assigned to a person who does not work here.
 *
 * The old text columns are kept and backfilled *from* the new keys rather than
 * dropped, because they are what the list screens already print.
 */
return new class extends Migration
{
    public function up(): void
    {
        // People belong to a department, and carry the public identity the
        // website's team page shows.
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('department_id')->nullable()->index();
            // Which group the website groups them under: Board, Management, IT…
            $table->string('collection')->nullable();
            // What the public site calls them, which is not always the job title.
            $table->string('public_title')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('show_on_website')->default(false);
            $table->unsignedInteger('position')->default(0);
        });

        // Stock is held against the item master, not a spelling of its name.
        Schema::table('inventory_stock', function (Blueprint $table) {
            $table->string('item_id')->nullable()->index();
        });

        // An asset is a capitalised item, held by a real person in a real place.
        Schema::table('assets_records', function (Blueprint $table) {
            $table->string('department_id')->nullable()->index();
            $table->string('assigned_user_id')->nullable()->index();
            $table->string('vendor_id')->nullable()->index();
        });

        // Who we buy from. Procurement raises orders against these, and an item
        // remembers its usual supplier.
        Schema::create('procurement_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organization_id')->index();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('tin')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->string('payment_terms')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('invoicing_items', function (Blueprint $table) {
            $table->string('vendor_id')->nullable()->index();
            // Where this item sits in the business: something we sell, something
            // we consume, or something we capitalise and track as an asset.
            $table->string('role')->default('product');
            $table->bigInteger('reorder_level')->default(0);
        });

        // The queries the list screens actually run, in the order they filter.
        Schema::table('invoicing_documents', function (Blueprint $table) {
            $table->index(['organization_id', 'doc_type', 'issue_date'], 'doc_org_type_date');
        });

        Schema::table('invoicing_payments', function (Blueprint $table) {
            $table->index(['organization_id', 'paid_on'], 'pay_org_date');
        });
    }

    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropColumn(['department_id', 'collection', 'public_title', 'photo_url', 'show_on_website', 'position']);
        });

        Schema::table('inventory_stock', fn (Blueprint $t) => $t->dropColumn('item_id'));
        Schema::table('assets_records', fn (Blueprint $t) => $t->dropColumn(['department_id', 'assigned_user_id', 'vendor_id']));
        Schema::table('invoicing_items', fn (Blueprint $t) => $t->dropColumn(['vendor_id', 'role', 'reorder_level']));
        Schema::table('invoicing_documents', fn (Blueprint $t) => $t->dropIndex('doc_org_type_date'));
        Schema::table('invoicing_payments', fn (Blueprint $t) => $t->dropIndex('pay_org_date'));
        Schema::dropIfExists('procurement_vendors');
    }
};
