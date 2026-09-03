<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's seat in an organization: which role they hold there.
 *
 * Separate from `users.role`, which governs the *website* admin (owner /
 * admin / user). The two answer different questions — "may you edit the public
 * site?" and "may you approve an invoice?" — and a charity routinely wants
 * someone who can do one and not the other.
 */
class OrganizationMember extends Model
{
    /**
     * The groups the public site sorts people into.
     *
     * This is the same list the website's team page renders as headings, which
     * is the point: there is one team, and the site shows a chosen part of it
     * rather than keeping a second copy of the same people.
     */
    public const COLLECTIONS = [
        'board' => 'Board',
        'management' => 'Management',
        'staff' => 'Staff',
        'it' => 'IT',
        'field' => 'Field',
        'finance' => 'Finance',
        'volunteer' => 'Volunteers',
    ];

    protected $fillable = [
        'organization_id', 'user_id', 'person_name', 'role', 'employee_type', 'job_title', 'active',
        'department_id', 'collection', 'public_title', 'photo_url', 'show_on_website', 'position',
    ];

    protected $casts = [
        'active' => 'boolean',
        'show_on_website' => 'boolean',
        'position' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Departments\Models\Department::class, 'department_id');
    }

    public function collectionLabel(): string
    {
        return self::COLLECTIONS[$this->collection] ?? ($this->collection ?: __('Team'));
    }

    /** What the website calls this person: their public title, else the job title. */
    public function displayTitle(): string
    {
        return $this->public_title ?: ($this->job_title ?: '');
    }

    /**
     * The name to show.
     *
     * A seat held by a named person with no login carries `person_name`; a
     * seat held by a user takes the username. The former is how FGE's board
     * sits on the team without five unusable accounts existing for them.
     */
    public function displayName(): string
    {
        return $this->person_name ?: ($this->user?->username ?? '');
    }

    public function roleLabel(): string
    {
        return Access::roleLabel($this->role);
    }

    public function typeLabel(): string
    {
        return Access::employeeTypeLabel($this->employee_type);
    }

    public function can(string $action, ?string $module = null): bool
    {
        return $this->active && Access::can($this->role, $action, $this->employee_type, $module);
    }
}
