// Typeahead pickers.
//
// Turns `<input data-picker="customers" data-target="#customer_id">` into a
// search box that queries /admin/lookup/customers as you type and writes the
// chosen row's id into the hidden input it targets.
//
// This replaces selects that shipped every row to the browser: a form with two
// and a half thousand customers in a <select> is slow to render and impossible
// to scan, and it only gets worse as the business grows.

(function () {
    'use strict';

    const BASE = document.querySelector('meta[name="lookup-base"]')?.content || '/admin/lookup';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function debounce(fn, ms) {
        let t;
        return function (...a) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, a), ms);
        };
    }

    async function fetchRows(source, params) {
        const url = new URL(BASE + '/' + source, window.location.origin);
        Object.entries(params).forEach(([k, v]) => v && url.searchParams.set(k, v));

        const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' } });
        if (!res.ok) return [];

        return (await res.json()).results || [];
    }

    function build(input) {
        const source = input.dataset.picker;
        const target = document.querySelector(input.dataset.target);
        if (!source || !target) return;

        const box = document.createElement('div');
        box.className = 'picker-results';
        box.hidden = true;
        input.insertAdjacentElement('afterend', box);
        input.setAttribute('autocomplete', 'off');

        let rows = [];
        let active = -1;

        const close = () => { box.hidden = true; active = -1; };

        const choose = (row) => {
            target.value = row.id;
            input.value = row.label;
            close();
            // Let the host form react — the invoice line uses this to fill the
            // rate from the chosen item without a second round trip.
            input.dispatchEvent(new CustomEvent('picked', { detail: row, bubbles: true }));
        };

        const render = () => {
            box.innerHTML = '';

            if (!rows.length) {
                box.innerHTML = '<div class="picker-empty">' + (input.dataset.empty || 'No matches') + '</div>';
                box.hidden = false;
                return;
            }

            rows.forEach((row, i) => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'picker-row' + (i === active ? ' on' : '');
                el.innerHTML = '<span>' + escapeHtml(row.label) + '</span>' +
                    (row.meta ? '<em>' + escapeHtml(row.meta) + '</em>' : '');
                el.addEventListener('mousedown', (e) => { e.preventDefault(); choose(row); });
                box.append(el);
            });

            box.hidden = false;
        };

        const search = debounce(async () => {
            rows = await fetchRows(source, { q: input.value });
            active = -1;
            render();
        }, 180);

        input.addEventListener('input', () => {
            // Typing invalidates the stored id: the box must never show one
            // name while the form submits a different record.
            target.value = '';
            search();
        });

        input.addEventListener('focus', () => { if (input.value === '') search(); });
        input.addEventListener('blur', () => setTimeout(close, 120));

        input.addEventListener('keydown', (e) => {
            if (box.hidden || !rows.length) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
                render();
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault();
                choose(rows[active]);
            } else if (e.key === 'Escape') {
                close();
            }
        });

        // An edit form arrives holding an id but no label; ask for just that row.
        if (target.value && !input.value) {
            fetchRows(source, { id: target.value }).then((r) => {
                if (r.length) input.value = r[0].label;
            });
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function init(root) {
        (root || document).querySelectorAll('input[data-picker]:not([data-picker-ready])').forEach((el) => {
            el.setAttribute('data-picker-ready', '1');
            build(el);
        });
    }

    document.addEventListener('DOMContentLoaded', () => init());
    // Invoice lines are added after load, so newly inserted rows get pickers too.
    document.addEventListener('picker:refresh', (e) => init(e.target));
    window.initPickers = init;
})();
