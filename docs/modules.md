# Modules

A module is a directory under `modules/`. Nothing central lists them — the app
reads `modules/*/module.json` at boot — so adding one is a scaffold command and
some code, with no registry file to remember to edit.

```bash
php artisan module:init Expenses --label="Expenses" --icon=box --order=30 --plan=starter
```

With no argument it lists what is installed and flags anything malformed:

```bash
php artisan module:init
```

## What a module owns

```
modules/Crm/
  module.json                  slug, label, icon, order, enabled, requires_plan
  Http/Controllers/            web controllers extend App\...\ModuleController
  Models/                      its own Eloquent models
  routes/web.php               → /admin/m/crm      names: crm.*
  routes/api.php               → /api/crm          names: api.crm.*
  resources/views/             → crm::index, crm::customers.form
  database/migrations/         picked up by `php artisan migrate`
  config/config.php            merged under config('crm')
```

Route names and the view namespace are both prefixed with the slug, so two
modules can each have a `customers.index` without colliding.

## The three gates

A member needs **the grant**, **the module** and **the action**. They are
separate on purpose: collapsing them gives you `expenses.delete`,
`invoices.delete` and forty more strings that drift apart.

1. **The system grant** — `organization_modules`, set by a system admin at
   System → *organization*. This is what stops an organization granting itself
   Accounting by ticking a box.
2. **The plan** — narrows the grant further; see the table below.
3. **Module access** — per organization and editable by its owner at
   Organization → Access. Administration always keeps every module, because a
   matrix that could lock out every admin would leave nobody able to undo it.

**Actions** are fixed by role rank:

| Role | View | Add | Edit | Delete | Approve | Manage people |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| Administration | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Manager | ✓ | ✓ | ✓ | — | ✓ | — |
| Salesperson | ✓ | ✓ | ✓ | — | — | — |
| Employee | ✓ | ✓ | — | — | — | — |

Enforcement lives in two places, and both matter:

- `module:{slug}` middleware on every module route — this is what actually
  stops someone, and it also refuses **all writes** when the organization's
  plan has lapsed (reads still work; their data is theirs).
- `$this->authorizeAction('delete')` at the top of each write, for the
  per-action half.

The nav hides what a member cannot reach, but hiding is a convenience — never
the rule.

## Organization and plans

`Organization` is the tenant: it owns the websites, the people and the records.
Every new one starts on a 14-day trial with every module unlocked.

| Plan | Modules |
| --- | --- |
| Free Trial | everything, 14 days |
| Starter | crm, invoicing, items, expenses |
| Professional | + inventory, bookings, billing, reports |
| Enterprise | everything |

Changing the plan at Organization → Subscription records it. **No payment is
taken** — no processor is wired up, and a checkout that pretended otherwise
would be worse than none.

## Installed modules

| Slug | Plan | What it is |
| --- | --- | --- |
| `crm` | any | Customers and vendors — contacts, billing details, payment terms |
| `invoicing` | starter | Items, invoices/estimates/credit notes/bills, lines, payments |
| `expenses` | starter | Expense claims against accounts, with approval |
| `inventory` | professional | Stock by location, reorder levels, batches |
| `projects` | professional | Projects, budgets and billing method |
| `purchasing` | professional | Purchase orders to vendors |
| `fulfillment` | professional | Packages, carriers and tracking |
| `billing` | professional | Recurring subscriptions sold to your customers |
| `bookings` | professional | Services, staff and appointments |
| `procurement` | enterprise | Purchase requests and approvals |
| `accounting` | enterprise | Chart of accounts |
| `departments` | any | Departments, heads and budgets |

All are ports of `crates/knowlia-invoice`. Money is stored as **integer minor
units** throughout: `decimal` in SQLite is a float, and an invoice that adds up
a cent short is an invoice a customer disputes.

Most are built on `ResourceModuleController` + `App\Support\Field`, which
gives list/create/edit/delete from a field declaration — thirteen modules would
otherwise be thirteen copies of the same 120 lines. `crm` and `invoicing` have
real behaviour (line totals, payments, status transitions) and write their own
controllers instead; the base is a floor, not a ceiling.
