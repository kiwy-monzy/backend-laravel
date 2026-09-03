/**
 * The admin's data grid.
 *
 * One table for every list view in the product, built on Glide Data Grid so it
 * draws to a canvas rather than to thousands of DOM nodes — which is what lets
 * a list of sixty thousand invoices scroll without the browser labouring.
 *
 * A page declares its grid entirely in markup:
 *
 *   <div data-grid
 *        data-src="/admin/m/invoicing/invoices/data"
 *        data-columns='[{"id":"number","title":"Number","width":140}, …]'
 *        data-groups='[{"title":"Document","icon":"invoice","columns":["number","status"]}]'
 *        data-row-href="/admin/m/invoicing/invoices/{id}/edit"></div>
 *
 * so no list view has to know any of what follows.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { DataEditor, GridCellKind, GridColumnIcon } from '@glideapps/glide-data-grid';
import '@glideapps/glide-data-grid/dist/index.css';

/** Column kinds a page can ask for, mapped to how the grid draws them. */
const KIND = {
    text: GridCellKind.Text,
    number: GridCellKind.Number,
    money: GridCellKind.Text,
    date: GridCellKind.Text,
    badge: GridCellKind.Bubble,
    image: GridCellKind.Image,
    boolean: GridCellKind.Boolean,
    markdown: GridCellKind.Markdown,
};

/** Header icons, so a group of columns reads at a glance. */
const ICON = {
    invoice: GridColumnIcon.HeaderCode,
    money: GridColumnIcon.HeaderNumber,
    date: GridColumnIcon.HeaderDate,
    person: GridColumnIcon.HeaderSingleValue,
    image: GridColumnIcon.HeaderImage,
    text: GridColumnIcon.HeaderString,
    tag: GridColumnIcon.HeaderArray,
    number: GridColumnIcon.HeaderNumber,
    boolean: GridColumnIcon.HeaderBoolean,
};

function useDebounced(value, ms) {
    const [v, setV] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setV(value), ms);
        return () => clearTimeout(t);
    }, [value, ms]);
    return v;
}

function Grid({ config }) {
    const {
        src,
        columns: columnSpec,
        groups = [],
        rowHref,
        perPage = 100,
        filters: filterSpec = [],
        emptyText = 'Nothing here yet.',
    } = config;

    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState({});
    const [sort, setSort] = useState({ column: null, dir: 'asc' });
    const [loading, setLoading] = useState(true);

    const debouncedSearch = useDebounced(search, 250);
    const cache = useRef(new Map());

    // Which column each group covers, resolved to the group title the grid
    // shows above the header. Columns outside every group sit on their own.
    const columns = useMemo(
        () =>
            columnSpec.map((c) => ({
                id: c.id,
                title: c.title,
                width: c.width || 150,
                icon: ICON[c.icon || c.type] || ICON.text,
                group: groups.find((g) => g.columns.includes(c.id))?.title,
                themeOverride: c.mono ? { fontFamily: 'ui-monospace, Menlo, monospace' } : undefined,
            })),
        [columnSpec, groups]
    );

    const load = useCallback(async () => {
        setLoading(true);
        const url = new URL(src, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        if (debouncedSearch) url.searchParams.set('q', debouncedSearch);
        if (sort.column) {
            url.searchParams.set('sort', sort.column);
            url.searchParams.set('dir', sort.dir);
        }
        Object.entries(filters).forEach(([k, v]) => v && url.searchParams.set(`f[${k}]`, v));

        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            setRows(json.rows || []);
            setTotal(json.total || 0);
            cache.current.clear();
        } catch (e) {
            setRows([]);
            setTotal(0);
        } finally {
            setLoading(false);
        }
    }, [src, page, perPage, debouncedSearch, sort, filters]);

    useEffect(() => {
        load();
    }, [load]);

    // A change of search or filter invalidates the page you were on.
    useEffect(() => {
        setPage(1);
    }, [debouncedSearch, filters]);

    const getCellContent = useCallback(
        ([col, row]) => {
            const spec = columnSpec[col];
            const record = rows[row];
            const empty = { kind: GridCellKind.Text, data: '', displayData: '', allowOverlay: false };

            if (!spec || !record) return empty;

            const raw = record[spec.id];
            const kind = KIND[spec.type] || GridCellKind.Text;

            if (kind === GridCellKind.Image) {
                const urls = Array.isArray(raw) ? raw : raw ? [raw] : [];
                return { kind, data: urls, displayData: urls, allowOverlay: urls.length > 0, allowAdd: false };
            }

            if (kind === GridCellKind.Bubble) {
                const items = Array.isArray(raw) ? raw : raw ? [String(raw)] : [];
                return { kind, data: items, allowOverlay: false };
            }

            if (kind === GridCellKind.Boolean) {
                return { kind, data: !!raw, allowOverlay: false };
            }

            if (kind === GridCellKind.Number) {
                return {
                    kind,
                    data: Number(raw) || 0,
                    displayData: raw === null || raw === undefined ? '' : String(raw),
                    allowOverlay: false,
                    contentAlign: 'right',
                };
            }

            const text = raw === null || raw === undefined ? '' : String(raw);

            return {
                kind: GridCellKind.Text,
                data: text,
                displayData: text,
                allowOverlay: text.length > 24, // long values open rather than clip
                contentAlign: spec.type === 'money' ? 'right' : undefined,
            };
        },
        [rows, columnSpec]
    );

    const onHeaderClicked = useCallback(
        (col) => {
            const spec = columnSpec[col];
            if (!spec || spec.sortable === false) return;
            setSort((s) =>
                s.column === spec.id ? { column: spec.id, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { column: spec.id, dir: 'asc' }
            );
        },
        [columnSpec]
    );

    const onRowClicked = useCallback(
        ([, row]) => {
            const record = rows[row];
            if (!record || !rowHref) return;
            // `__ID__` rather than `{id}`: Laravel's route() percent-encodes
            // braces, which would arrive here as %7Bid%7D and never match.
            window.location.href = rowHref.replace('__ID__', encodeURIComponent(record.id));
        },
        [rows, rowHref]
    );

    const pages = Math.max(1, Math.ceil(total / perPage));

    return (
        <div className="fge-grid">
            <div className="fge-grid-bar">
                <input
                    className="fge-grid-search"
                    type="search"
                    placeholder="Search…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {filterSpec.map((f) => (
                    <select
                        key={f.id}
                        value={filters[f.id] || ''}
                        onChange={(e) => setFilters((prev) => ({ ...prev, [f.id]: e.target.value }))}
                    >
                        <option value="">{f.title}</option>
                        {f.options.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                ))}

                <span className="fge-grid-spacer" />
                <span className="fge-grid-count">
                    {loading ? 'Loading…' : `${total.toLocaleString()} rows`}
                </span>
            </div>

            {rows.length === 0 && !loading ? (
                <p className="fge-grid-empty">{emptyText}</p>
            ) : (
                <div className="fge-grid-canvas">
                    <DataEditor
                        columns={columns}
                        rows={rows.length}
                        getCellContent={getCellContent}
                        onHeaderClicked={onHeaderClicked}
                        onCellActivated={onRowClicked}
                        rowMarkers="number"
                        smoothScrollX
                        smoothScrollY
                        width="100%"
                        height={Math.min(620, 44 + rows.length * 34)}
                        getCellsForSelection
                    />
                </div>
            )}

            {pages > 1 && (
                <div className="fge-grid-pager">
                    <button type="button" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        ‹
                    </button>
                    <span>
                        Page {page.toLocaleString()} of {pages.toLocaleString()}
                    </span>
                    <button type="button" disabled={page >= pages} onClick={() => setPage((p) => p + 1)}>
                        ›
                    </button>
                </div>
            )}
        </div>
    );
}

function parse(el, name, fallback) {
    const raw = el.dataset[name];
    if (!raw) return fallback;
    try {
        return JSON.parse(raw);
    } catch (e) {
        return fallback;
    }
}

function mount(root) {
    (root || document).querySelectorAll('[data-grid]:not([data-grid-ready])').forEach((el) => {
        el.setAttribute('data-grid-ready', '1');

        createRoot(el).render(
            <Grid
                config={{
                    src: el.dataset.src,
                    columns: parse(el, 'columns', []),
                    groups: parse(el, 'groups', []),
                    filters: parse(el, 'filters', []),
                    rowHref: el.dataset.rowHref,
                    perPage: Number(el.dataset.perPage) || 100,
                    emptyText: el.dataset.empty || 'Nothing here yet.',
                }}
            />
        );
    });
}

document.addEventListener('DOMContentLoaded', () => mount());
window.mountGrids = mount;
