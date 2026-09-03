<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Models\Customer;

/**
 * An invoice, estimate, credit note or bill.
 *
 * One table for all four because they differ only in what they are called and
 * which way the money points — the Rust model reached the same conclusion, and
 * four near-identical tables would mean four of every query.
 */
class Document extends Model
{
    public const TYPES = [
        'estimate' => 'Quote',
        'invoice' => 'Invoice',
        'sales_receipt' => 'Sales receipt',
        'credit_note' => 'Credit note',
        'purchase_order' => 'Purchase order',
        'bill' => 'Bill',
    ];

    /**
     * Which way the money points, per type.
     *
     * Quotes, invoices, receipts and credit notes are raised *to* a customer;
     * purchase orders and bills are raised *by* a supplier. The list screens
     * read this to label the counterparty column correctly, and Procurement
     * shows only the inbound half.
     */
    public const INBOUND = ['purchase_order', 'bill'];

    /**
     * Paid at the moment they are raised, so they carry no debt.
     *
     * A sales receipt with a due date and an outstanding balance is a
     * contradiction: the money already changed hands. The lists and forms ask
     * this before showing either column.
     */
    public const SETTLED_ON_ISSUE = ['sales_receipt'];

    /** Types that promise nothing yet — no money is owed on a quote. */
    public const NOT_PAYABLE = ['estimate'];

    /** True when this type never carries a balance. */
    public function settlesOnIssue(): bool
    {
        return in_array($this->doc_type, self::SETTLED_ON_ISSUE, true);
    }

    public function isInbound(): bool
    {
        return in_array($this->doc_type, self::INBOUND, true);
    }

    /** What the second date column means for this type, or null if it has none. */
    public static function dueLabelFor(string $type): ?string
    {
        return match ($type) {
            'sales_receipt' => null,
            'estimate' => __('Valid until'),
            'purchase_order' => __('Expected'),
            default => __('Due'),
        };
    }

    /** Who the document is with. */
    public static function partyLabelFor(string $type): string
    {
        return in_array($type, self::INBOUND, true) ? __('Supplier') : __('Customer');
    }

    /** Whether a balance column means anything for this type. */
    public static function showsBalanceFor(string $type): bool
    {
        return ! in_array($type, array_merge(self::SETTLED_ON_ISSUE, self::NOT_PAYABLE), true);
    }

    /** The word for the money column: a quote is priced, an invoice is owed. */
    public static function totalLabelFor(string $type): string
    {
        return $type === 'estimate' ? __('Quoted') : __('Total');
    }

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'void' => 'Void',
    ];

    /** Prefixes for generated numbers, per type. */
    public const PREFIXES = [
        'invoice' => 'INV',
        'estimate' => 'QUO',
        'sales_receipt' => 'REC',
        'credit_note' => 'CN',
        'purchase_order' => 'PO',
        'bill' => 'BILL',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'invoicing_documents';

    protected $fillable = [
        'id', 'organization_id', 'customer_id', 'doc_type', 'number', 'status',
        'issue_date', 'due_date', 'currency',
        'subtotal_minor', 'tax_minor', 'discount_minor', 'total_minor', 'paid_minor',
        'reference', 'notes', 'terms',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_minor' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class, 'document_id')->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'document_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function balanceMinor(): int
    {
        return $this->total_minor - $this->paid_minor;
    }

    public function formattedTotal(): string
    {
        return Money::format($this->total_minor, $this->currency);
    }

    public function formattedBalance(): string
    {
        return Money::format($this->balanceMinor(), $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->balanceMinor() > 0
            && ! in_array($this->status, ['void', 'paid'], true)
            && $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? self::STATUSES['overdue'] : (self::STATUSES[$this->status] ?? $this->status);
    }

    /**
     * Recompute the totals from the lines and the payments.
     *
     * Called after every write that could change them. Totals are stored
     * rather than derived on read because a list of two hundred invoices
     * should not load two hundred sets of lines to show a column of numbers.
     */
    public function recalculate(): static
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($this->lines as $line) {
            $amount = $line->computedAmountMinor();
            $subtotal += $amount;
            $tax += (int) round($amount * $line->tax_percent / 100);
        }

        $this->subtotal_minor = $subtotal;
        $this->tax_minor = $tax;
        $this->total_minor = max(0, $subtotal + $tax - $this->discount_minor);
        $this->paid_minor = (int) $this->payments()->sum('amount_minor');

        // Status follows the money, except for the two an operator sets by
        // hand: a draft stays a draft until it is sent, and a void stays void.
        if (! in_array($this->status, ['draft', 'void'], true)) {
            $this->status = match (true) {
                $this->paid_minor >= $this->total_minor && $this->total_minor > 0 => 'paid',
                $this->paid_minor > 0 => 'partially_paid',
                default => 'sent',
            };
        }

        $this->save();

        return $this;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->doc_type,
            'number' => $this->number,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => Money::toDecimal($this->subtotal_minor),
            'tax' => Money::toDecimal($this->tax_minor),
            'total' => Money::toDecimal($this->total_minor),
            'paid' => Money::toDecimal($this->paid_minor),
            'balance' => Money::toDecimal($this->balanceMinor()),
        ];
    }

    /**
     * The next number for a type, per organization.
     *
     * Counts existing rows rather than keeping a counter column, which is
     * fine at this scale and cannot drift out of step with the table.
     */
    public static function nextNumber(string $organizationId, string $type): string
    {
        $prefix = self::PREFIXES[$type] ?? 'DOC';
        $count = self::where('organization_id', $organizationId)->where('doc_type', $type)->count();

        return sprintf('%s-%05d', $prefix, $count + 1);
    }
}
