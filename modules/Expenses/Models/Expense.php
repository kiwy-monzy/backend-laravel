<?php

namespace Modules\Expenses\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'reimbursed' => 'Reimbursed', 'rejected' => 'Rejected'];

    public const METHODS = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'card' => 'Card', 'cheque' => 'Cheque'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'expenses_records';

    protected $fillable = [
        'project_id',
        'contract_id',
        'department_id',
        'account_id',
        'vendor_id',
        'id', 'organization_id', 'reference', 'account', 'vendor', 'amount_minor', 'currency', 'spent_on', 'status', 'payment_method', 'notes', 'receipt_url', 'billable',
    ];

    protected $casts = [
        'amount_minor' => 'integer', 'spent_on' => 'date', 'billable' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\Modules\Procurement\Models\Vendor::class, 'vendor_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Account::class, 'account_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Departments\Models\Department::class, 'department_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(\Modules\Contracts\Models\Contract::class, 'contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Projects\Models\Project::class, 'project_id');
    }
}
