<?php

namespace Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Tickets\Models\Ticket;

class TicketController extends ResourceModuleController
{
    protected string $module = 'tickets';

    protected string $model = Ticket::class;

    protected string $title = 'Ticket';

    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['subject', 'requester', 'requester_email', 'reference'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'ticket'];

    protected function routeBase(): string
    {
        return 'tickets.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('subject', __('Subject'))->required(),
            Field::text('requester', __('Raised by')),
            Field::email('requester_email', __('Email')),
            Field::select('category', __('Category'), Ticket::CATEGORIES, 'question'),
            Field::select('priority', __('Priority'), Ticket::PRIORITIES, 'normal'),
            Field::select('status', __('Status'), Ticket::STATUSES, 'open'),
            Field::text('assigned_to', __('Assigned to')),
            Field::date('due_on', __('Due on')),
            Field::date('resolved_on', __('Resolved on')),
            Field::textarea('description', __('Description')),
            Field::textarea('resolution', __('Resolution')),
        ];
    }

    protected function columns(): array
    {
        return [
            'subject' => __('Subject'),
            'requester' => __('Raised by'),
            'category' => __('Category'),
            'priority' => __('Priority'),
            'status' => __('Status'),
            'due_on' => __('Due'),
        ];
    }
}
