<?php

namespace Modules\Workerly\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    public const TYPES = ['salesperson' => 'Salesperson', 'engineer' => 'Engineer', 'technician' => 'Technician', 'supervisor' => 'Supervisor', 'driver' => 'Driver', 'storekeeper' => 'Storekeeper', 'accountant' => 'Accountant', 'labourer' => 'Labourer', 'other' => 'Other'];

    public const STATUSES = ['logged' => 'Logged', 'approved' => 'Approved', 'invoiced' => 'Invoiced', 'rejected' => 'Rejected'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'workerly_shifts';

    protected $fillable = [
        'id', 'organization_id', 'employee', 'employee_type', 'contract_id', 'project', 'activity', 'worked_on', 'hours', 'status', 'rate_minor', 'billable', 'notes',
    ];

    protected $casts = [
        'worked_on' => 'date', 'hours' => 'float', 'rate_minor' => 'integer', 'billable' => 'boolean',
    ];

    /**
     * The contract this labour was worked against.
     *
     * The column and the contract's own read-back existed, but no relation did
     * — so nothing could go from a shift to the contract paying for it, which
     * is the direction anyone reviewing a timesheet actually travels.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(\Modules\Contracts\Models\Contract::class, 'contract_id');
    }

    /** True when the hours belong to a contract rather than to general work. */
    public function isContractWork(): bool
    {
        return $this->contract_id !== null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
