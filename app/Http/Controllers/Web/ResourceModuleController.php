<?php

namespace App\Http\Controllers\Web;

use App\Support\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * List / create / edit / delete for one model, driven by a field list.
 *
 * **Thirteen modules would otherwise be thirteen copies of the same 120
 * lines.** The parts that actually differ between an expense and a booking are
 * the table, the columns and the validation — so a subclass declares those and
 * inherits the rest. Anything with real behaviour (invoicing's totals, stock
 * movements) overrides or ignores this and writes its own controller; this is
 * the floor, not a ceiling.
 *
 * Every query is scoped to the organization by `ModuleController::scopedToOrg`,
 * and every write checks the action, so a subclass cannot forget either.
 */
abstract class ResourceModuleController extends ModuleController
{
    /** @var class-string<Model> */
    protected string $model;

    /** Singular human label, e.g. "Expense". */
    protected string $title = 'Record';

    /** Column the list sorts by. */
    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    /** Columns the search box looks in. */
    protected array $searchable = [];

    /**
     * The fields, as `Field` objects.
     *
     * @return array<int,Field>
     */
    abstract protected function fields(): array;

    /** Columns shown in the list, as `attribute => label`. */
    abstract protected function columns(): array;

    /**
     * The same columns, readable from outside.
     *
     * Each module's overview page shows a recent-records table and should not
     * have to restate the column list its own resource controller already
     * declares — two lists that have to agree is two lists that will not.
     */
    public function listColumns(): array
    {
        return $this->columns();
    }

    public function index(Request $request)
    {
        $query = $this->scopedToOrg($this->model::query());

        if ($term = $request->query('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        return view('modules.resource-index', [
            'records' => $query->orderBy($this->orderBy, $this->orderDirection)->paginate(30)->withQueryString(),
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'title' => $this->title,
            'module' => $this->module,
            'routeBase' => $this->routeBase(),
            'q' => $request->query('q'),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
            // What the data grid needs to draw itself.
            'gridColumns' => $this->gridColumns(),
            'gridFilters' => $this->gridFilters(),
            'gridSource' => \Illuminate\Support\Facades\Route::has($this->routeBase() . '.data')
                ? route($this->routeBase() . '.data')
                : null,
        ]);
    }

    /**
     * The same list as JSON, for the data grid.
     *
     * The grid asks for a page at a time and does its own searching, sorting
     * and filtering, so this is deliberately thin: it never loads more than one
     * screenful, and the columns it returns are the ones the module already
     * declares for its table. Cells are pre-formatted here rather than in the
     * browser, because the money and date rules belong with the model.
     */
    /**
     * Relations the grid prints, eager-loaded.
     *
     * Without this a hundred-row page that shows a related name is a hundred
     * and one queries — the classic list-view collapse that only appears once
     * the table has real data in it.
     *
     * @var array<int,string>
     */
    protected array $gridWith = [];

    public function data(Request $request)
    {
        $query = $this->scopedToOrg($this->model::query())->with($this->gridWith);

        if ($term = $request->query('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        // Filters arrive as f[column]=value, and only columns this module
        // actually lists may be filtered — a query string must not become a way
        // to probe columns the module does not show.
        $listable = array_keys($this->columns());

        foreach ((array) $request->query('f', []) as $column => $value) {
            if ($value !== '' && $value !== null && in_array($column, $listable, true)) {
                $query->where($column, $value);
            }
        }

        $sort = $request->query('sort');
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $query->orderBy(
            in_array($sort, $listable, true) ? $sort : $this->orderBy,
            $sort ? $direction : $this->orderDirection,
        );

        $perPage = min(500, max(10, (int) $request->query('per_page', 100)));
        $page = $query->paginate($perPage);

        return response()->json([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'rows' => collect($page->items())->map(fn ($record) => $this->gridRow($record))->all(),
        ]);
    }

    /**
     * One record as the grid wants it: the listed columns, plus its id so a
     * row click knows where to go.
     */
    protected function gridRow($record): array
    {
        $row = ['id' => $record->getKey()];

        foreach (array_keys($this->columns()) as $column) {
            $row[$column] = $this->gridValue($record, $column);
        }

        return $row;
    }

    /**
     * The grid's columns, derived from the fields the module already declares.
     *
     * A module states its columns once, in `columns()`, and its field types
     * once, in `fields()`; the grid takes both rather than asking for a third
     * list that would have to be kept in agreement with them.
     *
     * @return array<int,array{id:string,title:string,type:string,icon:string,width:int}>
     */
    protected function gridColumns(): array
    {
        $types = [];

        foreach ($this->fields() as $field) {
            $types[$field->name] = $field->type;
        }

        $map = [
            'money' => ['money', 'money', 130],
            'number' => ['number', 'number', 110],
            'date' => ['date', 'date', 120],
            'checkbox' => ['boolean', 'boolean', 90],
            'select' => ['badge', 'tag', 140],
            'image' => ['image', 'image', 90],
            'images' => ['image', 'image', 120],
            'textarea' => ['text', 'text', 220],
        ];

        $columns = [];

        foreach ($this->columns() as $name => $label) {
            [$type, $icon, $width] = $map[$types[$name] ?? 'text'] ?? ['text', 'text', 160];

            $columns[] = [
                'id' => $name,
                'title' => $label,
                'type' => $type,
                'icon' => $icon,
                'width' => $width,
            ];
        }

        return $columns;
    }

    /**
     * Dropdown filters for the grid: every listed column that is a fixed set of
     * values already knows its own options.
     */
    protected function gridFilters(): array
    {
        $listed = array_keys($this->columns());
        $filters = [];

        foreach ($this->fields() as $field) {
            if ($field->type !== 'select' || ! in_array($field->name, $listed, true)) {
                continue;
            }

            $filters[] = [
                'id' => $field->name,
                'title' => $field->label,
                'options' => collect($field->options)
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
            ];
        }

        return $filters;
    }

    /** How one cell is rendered. Modules override to add their own shapes. */
    protected function gridValue($record, string $column): mixed
    {
        // A `_minor` column is money held as an integer; the grid must never
        // print 55303695 where the reader expects 553,036.95.
        if (str_ends_with($column, '_minor')) {
            return \Modules\Invoicing\Models\Money::format(
                (int) $record->{$column},
                $this->organization()?->currency ?? 'TZS',
            );
        }

        $value = $record->{$column};

        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value->toDateString();
        }

        if (is_bool($value)) {
            return $value;
        }

        // A select's stored key is not what a reader wants: `in_store` is a
        // database value, "In store" is the label the module already declares.
        if (is_string($value) && $label = $this->optionLabel($column, $value)) {
            return $label;
        }

        return $value;
    }

    /** The human label for a select value, from the module's own field options. */
    private function optionLabel(string $column, string $value): ?string
    {
        foreach ($this->fields() as $field) {
            if ($field->name === $column && $field->type === 'select') {
                return $field->options[$value] ?? null;
            }
        }

        return null;
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('modules.resource-form', [
            'record' => new $this->model($this->defaults()),
            'fields' => $this->fields(),
            'title' => $this->title,
            'routeBase' => $this->routeBase(),
            'organization' => $this->organization(),
        ] + $this->formExtras(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $record = $this->model::create($this->validated($request) + $this->identity());

        return redirect()
            ->route($this->routeBase() . '.edit', $record)
            ->with('status', __(':title created.', ['title' => $this->title]));
    }

    public function edit(string $id)
    {
        $record = $this->findScoped($id);

        return view('modules.resource-form', [
            'record' => $record,
            'fields' => $this->fields(),
            'title' => $this->title,
            'routeBase' => $this->routeBase(),
            'organization' => $this->organization(),
        ] + $this->formExtras($record));
    }

    /**
     * Extra view data for the shared form, e.g. a module-specific action.
     *
     * A module that needs one more button under the form — "turn this request
     * into a booking" — would otherwise have to copy the whole form blade to
     * add it, and every later change to the form would then have to be made
     * twice. Return `['formActions' => 'servicehub::request-actions']` and the
     * blade includes it; return nothing and the form is unchanged.
     *
     * @param  Model|null  $record  Null on the create form.
     */
    protected function formExtras(?Model $record): array
    {
        return [];
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAction('edit');

        $this->findScoped($id)->update($this->validated($request));

        return back()->with('status', __(':title saved.', ['title' => $this->title]));
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAction('delete');

        $this->findScoped($id)->delete();

        return redirect()
            ->route($this->routeBase() . '.index')
            ->with('status', __(':title deleted.', ['title' => $this->title]));
    }

    /**
     * Look up within this organization.
     *
     * Not route-model binding, which resolves on the primary key alone and
     * would hand one organization another's record.
     */
    protected function findScoped(string $id): Model
    {
        $record = $this->scopedToOrg($this->model::query())->find($id);

        if (! $record) {
            throw new NotFoundHttpException('No such ' . Str::lower($this->title) . '.');
        }

        return $record;
    }

    /**
     * Columns filled by the numbering service instead of by the user, as
     * `column => sequence key`.
     *
     * A reference the system can allocate is not a question worth asking: hand
     * typing meant two people could invent the same code and everyone invented
     * a different shape. Declare it here and the field disappears from the
     * form, filling itself on save.
     *
     * @var array<string,string>
     */
    protected array $generated = [];

    /** Values stamped on every new row. */
    protected function identity(): array
    {
        $model = new $this->model;

        $values = [
            'id' => $model->getIncrementing() ? null : (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
        ];

        foreach ($this->generated as $column => $key) {
            $values[$column] = \App\Support\Sequences::next($this->organizationId(), $key);
        }

        return array_filter($values, fn ($v) => $v !== null);
    }

    /** Attributes a blank form starts with. */
    protected function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            if ($field->default !== null) {
                $defaults[$field->name] = $field->default;
            }
        }

        return $defaults;
    }

    protected function validated(Request $request): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            $rules[$field->name] = $field->rules();
        }

        $data = $request->validate($rules);

        // Checkboxes post nothing when unticked, and `nullable` leaves the key
        // out entirely — both of which read as "unchanged" rather than "off"
        // unless every field is filled in explicitly.
        foreach ($this->fields() as $field) {
            $data[$field->name] = $field->type === 'checkbox'
                ? $request->boolean($field->name)
                : ($data[$field->name] ?? $field->default);
        }

        return $data;
    }

    /** Route name prefix, e.g. `expenses.records`. */
    abstract protected function routeBase(): string;
}
