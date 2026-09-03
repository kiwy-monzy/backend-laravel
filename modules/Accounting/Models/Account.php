<?php

namespace Modules\Accounting\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    public const TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'id', 'organization_id', 'code', 'name', 'account_type', 'parent_code', 'opening_balance_minor', 'currency', 'active', 'description',
    ];

    protected $casts = [
        'opening_balance_minor' => 'integer', 'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
