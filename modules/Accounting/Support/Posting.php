<?php

namespace Modules\Accounting\Support;

use Modules\Expenses\Models\Expense;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Payment;

/**
 * What each thing does to the books.
 *
 * One method per kind of business event, each returning a balanced entry. The
 * double entries themselves are ordinary bookkeeping, written out here so they
 * can be read and argued with rather than buried in a controller:
 *
 *   invoice   debit receivable        credit sales, credit VAT payable
 *   receipt   debit bank              credit sales, credit VAT payable
 *   bill      debit expenses, VAT     credit payable
 *   payment   debit bank              credit receivable
 *   expense   debit expenses          credit bank (or payable, if unpaid)
 *
 * Quotes and purchase orders post nothing: a quotation is an offer and an order
 * is a promise, and neither has yet moved any money.
 */
final class Posting
{
    /** Document types that are not transactions and must never post. */
    public const NEVER_POSTS = ['estimate', 'purchase_order'];

    /** Post a document, or remove its entry when it no longer qualifies. */
    public static function document(Document $document): void
    {
        $organizationId = $document->organization_id;
        $source = 'document';

        // A void document, a draft, or a type that is only ever an offer, has
        // no place in the books — and if it was there before, it comes out.
        if (in_array($document->doc_type, self::NEVER_POSTS, true)
            || in_array($document->status, ['draft', 'void'], true)) {
            Ledger::unpost($organizationId, $source, $document->id);

            return;
        }

        $net = (int) $document->subtotal_minor - (int) $document->discount_minor;
        $tax = (int) $document->tax_minor;
        $total = (int) $document->total_minor;

        // A credit note is an invoice in reverse, so the same lines run the
        // other way rather than being a second set of rules to keep in step.
        $sign = $document->doc_type === 'credit_note' ? -1 : 1;

        $lines = match ($document->doc_type) {
            'sales_receipt' => [
                ['account' => 'bank', 'debit' => $total, 'memo' => __('Received')],
                ['account' => 'sales', 'credit' => $net],
                ['account' => 'tax_payable', 'credit' => $tax],
            ],
            'bill' => [
                ['account' => 'expense', 'debit' => $net],
                ['account' => 'tax_payable', 'debit' => $tax],
                ['account' => 'payable', 'credit' => $total, 'memo' => __('Owed to supplier')],
            ],
            default => [
                ['account' => 'receivable', 'debit' => $sign * $total, 'memo' => __('Owed by customer')],
                ['account' => 'sales', 'credit' => $sign * $net],
                ['account' => 'tax_payable', 'credit' => $sign * $tax],
            ],
        };

        Ledger::post(
            $organizationId,
            $source,
            $document->id,
            $document->issue_date?->toDateString() ?? now()->toDateString(),
            trim((Document::TYPES[$document->doc_type] ?? __('Document')) . ' ' . $document->number),
            $lines,
            $document->number,
        );
    }

    /**
     * A payment moves money from what a customer owes into the bank.
     *
     * The sale itself was recognised when the invoice was raised, so a payment
     * recognises no income — recording it as sales again is the classic way to
     * double a year's revenue.
     */
    public static function payment(Payment $payment): void
    {
        $amount = (int) $payment->amount_minor;

        if ($amount === 0) {
            return;
        }

        Ledger::post(
            $payment->organization_id,
            'payment',
            $payment->id,
            $payment->paid_on?->toDateString() ?? now()->toDateString(),
            __('Payment received') . ($payment->reference ? ' ' . $payment->reference : ''),
            [
                ['account' => 'bank', 'debit' => $amount, 'memo' => $payment->methodLabel()],
                ['account' => 'receivable', 'credit' => $amount],
            ],
            $payment->reference,
        );
    }

    /** An expense is a cost, paid now or owed. */
    public static function expense(Expense $expense): void
    {
        $amount = (int) $expense->amount_minor;

        if ($amount === 0 || in_array($expense->status, ['draft', 'rejected'], true)) {
            Ledger::unpost($expense->organization_id, 'expense', $expense->id);

            return;
        }

        // Paid expenses leave the bank; approved-but-unpaid ones are owed.
        $credit = $expense->status === 'paid' ? 'bank' : 'payable';

        Ledger::post(
            $expense->organization_id,
            'expense',
            $expense->id,
            $expense->spent_on?->toDateString() ?? now()->toDateString(),
            trim(__('Expense') . ' ' . ($expense->reference ?? '')),
            [
                ['account' => 'expense', 'debit' => $amount, 'memo' => $expense->account],
                ['account' => $credit, 'credit' => $amount],
            ],
            $expense->reference,
        );
    }
}
