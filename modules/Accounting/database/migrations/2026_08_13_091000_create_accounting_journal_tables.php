<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry journal: the book every other book is derived from.
 *
 * An entry is a dated event with a memo; its lines carry the debits and credits
 * and must balance. The general ledger, the trial balance and the financial
 * statements are all *folds over these lines*, never stored totals — the same
 * reasoning as the IPC engine's certificate chain, and for the same reason: a
 * stored balance is a balance that can drift from its entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organization_id')->index();
            $table->string('number')->nullable();
            $table->date('entry_date')->index();
            $table->string('memo')->nullable();
            $table->string('reference')->nullable();
            // Where the entry came from: 'manual', or a module that posted it.
            $table->string('source')->default('manual');
            $table->timestamps();
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_id')->index();
            $table->string('account_id')->index();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('memo')->nullable();
            $table->unsignedInteger('position')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
    }
};
