<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves a query to the data grid.
 *
 * `ResourceModuleController` gets this for free, but the hand-written list
 * views — invoices, customers, items — each have their own controller and their
 * own idea of a row. Rather than copy the paging, searching, sorting and
 * filtering into every one of them, they hand their query and a column map to
 * this and get the endpoint's whole behaviour.
 *
 * Columns are declared once, as `id => [title, type, value]`, and the same
 * declaration drives both the JSON and the grid's header.
 */
final class GridSource
{
    /**
     * @param  array<string,array{title:string,type?:string,icon?:string,width?:int,value?:callable,sort?:string}>  $columns
     */
    public function __construct(
        private Builder $query,
        private array $columns,
        private array $searchable = [],
    ) {}

    public static function make(Builder $query, array $columns, array $searchable = []): self
    {
        return new self($query, $columns, $searchable);
    }

    /** The column spec the grid needs, as `data-columns`. */
    public function spec(): array
    {
        $out = [];

        foreach ($this->columns as $id => $column) {
            $out[] = [
                'id' => $id,
                'title' => $column['title'],
                'type' => $column['type'] ?? 'text',
                'icon' => $column['icon'] ?? ($column['type'] ?? 'text'),
                'width' => $column['width'] ?? 150,
            ];
        }

        return $out;
    }

    /** One page of rows, formatted. */
    public function json(Request $request): JsonResponse
    {
        $query = clone $this->query;

        if (($term = trim((string) $request->query('q', ''))) !== '' && $this->searchable) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        foreach ((array) $request->query('f', []) as $column => $value) {
            if ($value !== '' && $value !== null && isset($this->columns[$column])) {
                // Only a column that declares how it sorts may be filtered on a
                // real database column; the rest are computed and have none.
                $target = $this->columns[$column]['sort'] ?? $column;
                $query->where($target, $value);
            }
        }

        $sort = $request->query('sort');

        if ($sort && isset($this->columns[$sort])) {
            $query->reorder(
                $this->columns[$sort]['sort'] ?? $sort,
                $request->query('dir') === 'desc' ? 'desc' : 'asc',
            );
        }

        $perPage = min(500, max(10, (int) $request->query('per_page', 100)));
        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(function ($record) {
            $row = ['id' => $record->getKey()];

            foreach ($this->columns as $id => $column) {
                $row[$id] = isset($column['value'])
                    ? ($column['value'])($record)
                    : $record->{$id};
            }

            return $row;
        });

        return response()->json([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'rows' => $rows->all(),
        ]);
    }
}
