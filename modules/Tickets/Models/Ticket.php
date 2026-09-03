<?php

namespace Modules\Tickets\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    public const CATEGORIES = ['question' => 'Question', 'fault' => 'Fault', 'request' => 'Request', 'complaint' => 'Complaint', 'billing' => 'Billing'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting on requester', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'support_tickets';

    protected $fillable = [
        'id', 'organization_id', 'reference', 'subject', 'requester', 'requester_email', 'customer_id', 'category', 'priority', 'status', 'assigned_to', 'due_on', 'resolved_on', 'description', 'resolution',
    ];

    protected $casts = [
        'due_on' => 'date', 'resolved_on' => 'date',
    ];

    /** The customer who raised it, when they are one of ours. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Crm\Models\Customer::class, 'customer_id');
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
