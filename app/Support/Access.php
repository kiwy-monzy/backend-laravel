<?php

namespace App\Support;

/**
 * Roles, employee types, actions and the permission matrix.
 *
 * **Two independent gates, both of which must open.** A member needs the
 * module (may I enter Expenses at all?) *and* the action (may I delete in
 * here?). Collapsing them into one list of "permissions" is what makes these
 * systems unmanageable — you end up with `expenses.delete`, `invoices.delete`
 * and forty more strings that drift apart.
 *
 * **Role is authority; employee type is job.** There used to be a
 * `salesperson` role wedged between manager and employee purely to grant
 * `edit`, which meant adding engineers or drivers would have grown the ladder
 * a rung per profession. Role now answers "how much authority" and the type
 * answers "what do they do" — the type is what shifts and contract activities
 * are recorded against, and a sales type carries one extra action rather than
 * a whole rung.
 */
final class Access
{
    /** Most powerful first. */
    public const ROLES = ['admin', 'manager', 'employee'];

    public const ROLE_LABELS = [
        'admin' => 'Administration',
        'manager' => 'Manager',
        'employee' => 'Employee',
    ];

    public const ROLE_HINTS = [
        'admin' => 'Everything, including deletes, approvals and managing people.',
        'manager' => 'Runs a module: view, add, edit and approve. No deletes, no user admin.',
        'employee' => 'View and add. A sales type may also edit customer-facing records.',
    ];

    /**
     * What kind of employee someone is.
     *
     * Descriptive, and used to schedule shifts and attribute contract
     * activities. Only `salesperson` changes what anyone may *do*.
     */
    public const EMPLOYEE_TYPES = [
        'salesperson' => 'Salesperson',
        'engineer' => 'Engineer',
        'technician' => 'Technician',
        'supervisor' => 'Supervisor',
        'driver' => 'Driver',
        'storekeeper' => 'Storekeeper',
        'accountant' => 'Accountant',
        'administrator' => 'Administrator',
        'labourer' => 'Labourer',
        'other' => 'Other',
    ];

    /** Types that may edit customer-facing records despite being employees. */
    public const SELLING_TYPES = ['salesperson', 'supervisor'];

    /** Modules a selling type gets that extra `edit` in. */
    public const CUSTOMER_FACING = ['crm', 'invoicing', 'bookings', 'billing', 'contracts', 'servicehub'];

    public const ACTIONS = ['view', 'add', 'edit', 'delete', 'approve', 'manage_users', 'export'];

    public const ACTION_LABELS = [
        'view' => 'View',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'approve' => 'Approve / send',
        'manage_users' => 'Manage people',
        'export' => 'Export data',
    ];

    /**
     * Modules every member reaches whatever the plan or the matrix says.
     *
     * `subscription` is here for a specific reason: an organization whose
     * trial has lapsed must still be able to open its own billing page, or the
     * only way out of an expired plan is a support ticket.
     */
    public const ALWAYS_AVAILABLE = ['subscription', 'settings'];

    /** Modules only an organization admin sees by default. */
    public const ADMIN_ONLY = ['admin', 'team', 'settings', 'subscription', 'audit-log'];

    /**
     * Whether a role may perform an action.
     *
     * `$employeeType` is only consulted for the sales exception, and only in
     * customer-facing modules — passing it elsewhere changes nothing.
     */
    public static function can(string $role, string $action, ?string $employeeType = null, ?string $module = null): bool
    {
        $base = match ($role) {
            'admin' => true,
            'manager' => in_array($action, ['view', 'add', 'edit', 'approve', 'export'], true),
            'employee' => in_array($action, ['view', 'add'], true),
            default => false,
        };

        if ($base) {
            return true;
        }

        // The one exception: a selling employee may edit the records they are
        // paid to keep accurate.
        if ($role === 'employee'
            && $action === 'edit'
            && in_array((string) $employeeType, self::SELLING_TYPES, true)
            && ($module === null || in_array($module, self::CUSTOMER_FACING, true))) {
            return true;
        }

        return false;
    }

    /**
     * The default module gate, before an organization overrides it.
     *
     * Admins get everything. Everyone else gets the business modules and is
     * kept out of the administrative ones.
     */
    public static function moduleAllowedByDefault(string $role, string $module): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if (in_array($module, self::ADMIN_ONLY, true)) {
            return in_array($module, self::ALWAYS_AVAILABLE, true);
        }

        return match ($role) {
            'manager' => true,
            'employee' => in_array($module, ['crm', 'invoicing', 'expenses', 'contracts', 'storage'], true),
            default => false,
        };
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst($role);
    }

    public static function employeeTypeLabel(?string $type): string
    {
        return $type ? (self::EMPLOYEE_TYPES[$type] ?? ucfirst($type)) : '—';
    }

    /** Rank on the ladder; lower is more powerful. */
    public static function rank(string $role): int
    {
        $index = array_search($role, self::ROLES, true);

        return $index === false ? count(self::ROLES) : $index;
    }

    public static function atLeast(string $role, string $needed): bool
    {
        return self::rank($role) <= self::rank($needed);
    }
}
