<?php

namespace Modules\Crm\Models;

use App\Models\Organization;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An enquiry that has not become a customer yet.
 *
 * Website contact forms land here, as do phone calls and walk-ins. `convert()`
 * is the one-way door to a Customer, and it keeps the link so a customer can
 * always be traced back to where they came from.
 */
class Lead extends Model
{
    public const SOURCES = [
        'website_form' => 'Website form',
        'phone' => 'Phone',
        'email' => 'Email',
        'walk_in' => 'Walk-in',
        'referral' => 'Referral',
        'campaign' => 'Campaign',
    ];

    /** The pipeline, in order. */
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'proposal' => 'Proposal sent',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    public const OPEN_STATUSES = ['new', 'contacted', 'qualified', 'proposal'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'crm_leads';

    protected $fillable = [
        'id', 'organization_id', 'website_id', 'customer_id',
        'name', 'email', 'phone', 'company', 'subject', 'message',
        'source', 'status', 'owner_id', 'value', 'follow_up_on', 'notes',
    ];

    protected $casts = [
        'value' => 'float',
        'follow_up_on' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /** The customer this lead became, once it was converted. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The team member chasing it. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Overdue follow-ups are the only thing on this list that is urgent. */
    public function isOverdue(): bool
    {
        return $this->follow_up_on && $this->isOpen() && $this->follow_up_on->isPast();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('company', 'like', $like)
            ->orWhere('subject', 'like', $like)
            ->orWhere('message', 'like', $like));
    }

    /**
     * Turn this lead into a customer, keeping the trail.
     *
     * Idempotent: a lead already converted returns the customer it made rather
     * than creating a second one, because "Convert" is a button someone will
     * click twice.
     */
    public function convert(): Customer
    {
        if ($this->customer_id && $existing = Customer::find($this->customer_id)) {
            return $existing;
        }

        $customer = Customer::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->organization_id,
            'contact_type' => 'customer',
            'display_name' => $this->company ?: $this->name,
            'company_name' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'currency' => $this->organization?->currency ?? 'TZS',
            'payment_terms' => 'due_on_receipt',
            'notes' => $this->message,
            'active' => true,
        ]);

        $this->update(['customer_id' => $customer->id, 'status' => 'won']);

        return $customer;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'subject' => $this->subject,
            'source' => $this->source,
            'status' => $this->status,
            'value' => $this->value,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at?->toRfc3339String(),
        ];
    }
}
