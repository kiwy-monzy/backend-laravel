<?php

namespace Modules\Crm\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    public const TYPES = ['customer' => 'Customer', 'vendor' => 'Vendor'];

    /** The terms Zoho ships with, which is what the invoice due-date maths expects. */
    public const PAYMENT_TERMS = [
        'due_on_receipt' => 'Due on receipt',
        'net_15' => 'Net 15',
        'net_30' => 'Net 30',
        'net_45' => 'Net 45',
        'net_60' => 'Net 60',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'crm_customers';

    protected $fillable = [
        'id', 'organization_id', 'contact_type', 'display_name', 'company_name',
        'salutation', 'first_name', 'last_name', 'email', 'phone', 'mobile', 'website',
        'currency', 'payment_terms', 'credit_limit', 'tax_number',
        'billing_street', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country',
        'contact_people', 'notes', 'active',
    ];

    protected $casts = [
        'contact_people' => 'array',
        'credit_limit' => 'float',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Days to add to an invoice date to get its due date. */
    public function termDays(): int
    {
        return (int) filter_var($this->payment_terms, FILTER_SANITIZE_NUMBER_INT) ?: 0;
    }

    public function termLabel(): string
    {
        return self::PAYMENT_TERMS[$this->payment_terms] ?? $this->payment_terms;
    }

    /** The address as one line, for an invoice header. */
    public function billingAddress(): string
    {
        return collect([
            $this->billing_street, $this->billing_city, $this->billing_state,
            $this->billing_postcode, $this->billing_country,
        ])->filter()->implode(', ');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('display_name', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'contact_type' => $this->contact_type,
            'display_name' => $this->display_name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms,
            'billing_address' => $this->billingAddress(),
            'active' => $this->active,
            'created_at' => $this->created_at?->toRfc3339String(),
        ];
    }
}
