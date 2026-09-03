// IPC workspace — the desktop (egui) app's nine views, driven by the mow-ipc
// engine compiled to WebAssembly. Nothing here computes a certificate: every
// figure comes back out of `compute()` / `formula()`, the same Rust arithmetic
// the desktop runs. This file is the shell — state, editing, rendering, saving.

const C = window.IPC;

let engine = null;         // { preset, compute, formula }
let project = null;        // the live Project object (contract, indices, period[])
let out = null;            // last compute() result
let tab = 'dashboard';
let certPeriod = 0;        // selected period index for the Certificate view
let formulaPeriod = 0;     // selected period index for the Formula view
let dirty = false;
let saveTimer = null;

const view = document.getElementById('ipc-view');
// Null-safe append: `mount(null)` would render the literal text "null",
// and every view uses `cond ? node : null` to omit sections.
function mount(...kids) {
    for (const k of kids.flat()) if (k != null && k !== false) view.append(k);
}
const statusEl = document.getElementById('ipc-status');
const saveBtn = document.getElementById('ipc-save');
const titleEl = document.getElementById('ipc-title');

// ---- formatting -----------------------------------------------------------

const CUR = C.currency || 'TZS';

function money(n) {
    if (n == null || isNaN(n)) return '—';
    const neg = n < 0;
    const s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (neg ? '(' + s + ')' : s);
}
function money0(n) {
    if (n == null || isNaN(n)) return '—';
    return Math.round(n).toLocaleString('en-US');
}
function pct(x, dp = 2) { return (x == null || isNaN(x)) ? '—' : (x * 100).toFixed(dp) + '%'; }
function num(x, dp = 4) { return (x == null || isNaN(x)) ? '—' : Number(x).toFixed(dp); }

// ---- tiny DOM builder -----------------------------------------------------

function h(tag, attrs = {}, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
        if (k === 'class') e.className = v;
        else if (k === 'html') e.innerHTML = v;
        else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2), v);
        else if (v === true) e.setAttribute(k, '');
        else if (v !== false && v != null) e.setAttribute(k, v);
    }
    for (const kid of kids.flat()) {
        if (kid == null || kid === false) continue;
        e.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
    }
    return e;
}

// A labelled number input bound to obj[key]. Calls edited() after change.
function field(label, obj, key, opts = {}) {
    const inp = h('input', {
        type: opts.type || 'number',
        step: opts.step || 'any',
        value: obj[key] ?? '',
        disabled: !C.canEdit,
    });
    inp.addEventListener('change', () => {
        obj[key] = opts.type === 'text' ? inp.value : parseFloat(inp.value || '0');
        edited();
    });
    return h('label', { class: 'ipc-f' }, h('span', {}, label), inp);
}

// ---- state ----------------------------------------------------------------

function recompute() {
    try {
        out = JSON.parse(engine.compute(JSON.stringify(project)));
    } catch (e) {
        out = { valid: false, errors: ['engine error: ' + e], variants: {} };
    }
}

function edited() {
    dirty = true;
    recompute();
    render();
    scheduleSave();
    if (saveBtn) saveBtn.disabled = false;
}

function scheduleSave() {
    if (!C.canEdit) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 1200);
    setStatus('Editing…');
}

async function save() {
    if (!C.canEdit || !dirty) return;
    setStatus('Saving…');
    try {
        const res = await fetch(C.urls.update, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrf, 'Accept': 'application/json' },
            body: JSON.stringify({
                name: project.contract?.project_name || C.name,
                contract_no: project.contract?.contract_no || C.contract_no,
                data: project,
                // The engine's own answer travels with the inputs, so the
                // contract page can report what has been certified without the
                // server re-doing arithmetic that already exists in Rust.
                summary: summarise(),
            }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        dirty = false;
        if (saveBtn) saveBtn.disabled = true;
        if (titleEl && project.contract?.project_name) titleEl.textContent = project.contract.project_name;
        setStatus('Saved ' + new Date().toLocaleTimeString());
    } catch (e) {
        setStatus('Save failed: ' + e.message);
    }
}


// What the rest of the product needs to know about this project: the closing
// position of the certificate chain, under the Single formula (which is the
// same figure as Traditional, and the one the certificate is issued on).
function summarise() {
    const certs = out?.variants?.single || [];
    const last = certs.length ? certs[certs.length - 1] : null;

    return {
        periods: certs.length,
        valid: !!out?.valid,
        certified_minor: last ? Math.round(last.certified_to_date * 100) : 0,
        retention_minor: last ? Math.round(last.cumulative_retention * 100) : 0,
        advance_recovered_minor: last ? Math.round(last.cumulative_advance_recovery * 100) : 0,
        price_adjustment_minor: Math.round(certs.reduce((s, c) => s + (c.price_adjustment || 0), 0) * 100),
        physical_progress: last ? last.physical_progress : 0,
    };
}

function setStatus(t) { if (statusEl) statusEl.textContent = t; }

// ---- views ----------------------------------------------------------------

function render() {
    view.innerHTML = '';
    ({ dashboard, contract, bills, indices, periods, certificate, formula, export: exportView }[tab] || dashboard)();
}

function card(title, ...body) {
    return h('div', { class: 'card' }, title ? h('h2', { style: 'margin-top:0' }, title) : null, ...body);
}

function stat(n, k, cls = '') {
    return h('div', { class: 'stat ' + cls }, h('div', { class: 'n' }, n), h('div', { class: 'k' }, k));
}

function lastCert(variant = 'single') {
    const list = out?.variants?.[variant] || [];
    return list.length ? list[list.length - 1] : null;
}

function dashboard() {
    const d = out?.derived || {};
    const last = lastCert('single');
    const paTotal = (out?.variants?.single || []).reduce((s, c) => s + (c.price_adjustment || 0), 0);
    const vopUse = d.vat_amount != null && project.contract.vop_provision ? paTotal / project.contract.vop_provision : null;

    const lamps = [];
    lamps.push(h('span', { class: 'lamp ' + (out?.valid ? 'ok' : 'bad') }, out?.valid ? 'Valid' : (out?.errors?.length || 0) + ' problem(s)'));
    if (out?.disagreements?.length) lamps.push(h('span', { class: 'lamp bad' }, 'Traditional ≠ Single'));
    else lamps.push(h('span', { class: 'lamp ok' }, 'Traditional = Single'));
    const wcheck = Math.abs((d.bills_sum || 0) - (d.total_bills || 0));
    lamps.push(h('span', { class: 'lamp ' + (wcheck < (d.total_bills || 1) * 1e-6 + 1 ? 'ok' : 'warn') }, 'Bills reconcile'));

    mount(
        card(null, h('div', { class: 'ipc-lamps' }, ...lamps)),
        h('div', { class: 'grid c4' },
            stat(money0(d.contract_sum), 'Contract sum (' + CUR + ')'),
            stat(money0(d.advance_amount), 'Advance (' + CUR + ')'),
            stat(money0(d.retention_limit), 'Retention limit'),
            stat(money0(d.payment_limit), 'Payment ceiling'),
        ),
        h('div', { class: 'grid c4' },
            stat(last ? pct(last.physical_progress) : '—', 'Physical progress'),
            stat(last ? money0(last.net_payable) : '—', 'Latest net payable'),
            stat(money0(paTotal), 'Price adjustment to date'),
            stat(vopUse != null ? pct(vopUse) : '—', 'VOP provision used'),
        ),
        last && last.spi != null ? h('div', { class: 'grid c4' },
            stat(num(last.spi, 3), 'SPI (schedule)'),
            stat(last.cpi != null ? num(last.cpi, 3) : '—', 'CPI (cost)'),
            stat(pct(last.planned_progress), 'Planned progress'),
            stat(money0(last.certified_to_date), 'Certified to date'),
        ) : null,
        out?.period_count === 0 ? card('No periods yet', h('p', { class: 'dim' }, 'Add a period in the Periods tab to compute a certificate.')) : null,
        out?.errors?.length ? card('Validation', h('ul', { class: 'ipc-errs' }, ...out.errors.map(e => h('li', {}, e)))) : null,
    );
}

function contract() {
    const c = project.contract;
    const d = out?.derived || {};
    mount(
        card('Particulars',
            h('div', { class: 'ipc-form' },
                field('Project name', c, 'project_name', { type: 'text' }),
                field('Contract no.', c, 'contract_no', { type: 'text' }),
                field('Employer', c, 'employer', { type: 'text' }),
                field('Contractor', c, 'contractor', { type: 'text' }),
                field('Engineer', c, 'engineer', { type: 'text' }),
                field('Commencement date', c, 'commencement_date', { type: 'text' }),
                field('Original completion', c, 'original_completion_date', { type: 'text' }),
            ),
        ),
        card('Amounts (' + CUR + ')',
            h('div', { class: 'ipc-form' },
                field('Total of bills', c, 'total_bills'),
                field('Contingency provision', c, 'contingency_provision'),
                field('VOP provision', c, 'vop_provision'),
                field('Taxes & duties', c, 'taxes_duties'),
            ),
        ),
        card('Rates (fractions 0–1)',
            h('div', { class: 'ipc-form' },
                field('VAT rate', c, 'vat_rate'),
                field('Advance rate', c, 'advance_rate'),
                field('Advance recovery rate', c, 'advance_recovery_rate'),
                field('Recovery threshold', c, 'advance_recovery_threshold'),
                field('Payment ceiling', c, 'payment_ceiling'),
                field('Retention rate', c, 'retention_rate'),
                field('Retention limit rate', c, 'retention_limit_rate'),
            ),
        ),
        card('Derived (computed by the engine)',
            h('table', { class: 'kv' }, h('tbody', {},
                kv('Subtotal before VAT', money(d.subtotal_before_vat)),
                kv('VAT amount', money(d.vat_amount)),
                kv('Specified PS total', money(d.specified_ps_total)),
                kv('Contract sum', money(d.contract_sum), true),
                kv('Advance amount', money(d.advance_amount)),
                kv('Retention limit', money(d.retention_limit)),
                kv('Payment ceiling', money(d.payment_limit)),
                kv('Advance recovery starts at', money(d.advance_recovery_start)),
            )),
        ),
    );
}

function kv(k, v, strong = false) {
    return h('tr', {}, h('td', {}, k), h('td', { class: 'num' }, strong ? h('strong', {}, v) : v));
}

function bills() {
    const c = project.contract;
    const dtos = out?.bills || [];
    const weightById = {};
    dtos.forEach(b => weightById[b.code] = b.weight);

    const rows = c.bills.map((b, i) => h('tr', {},
        h('td', {}, inlineText(b, 'code')),
        h('td', {}, inlineText(b, 'name', 'wide')),
        h('td', { class: 'num' }, inlineNum(b, 'boq_value')),
        h('td', { class: 'num' }, pct(weightById[b.code] ?? 0)),
        h('td', {}, inlineText(b, 'live_indices_str', 'wide', () => {
            b.live_indices = (b.live_indices_str || '').split(',').map(s => s.trim()).filter(Boolean);
        })),
        h('td', { class: 'center' }, checkbox(b, 'non_adjustable')),
        h('td', {}, C.canEdit ? h('button', { class: 'btn tiny ghost', onclick: () => { c.bills.splice(i, 1); edited(); } }, '×') : null),
    ));

    // seed the editable csv mirror for live_indices
    c.bills.forEach(b => { if (b.live_indices_str == null) b.live_indices_str = (b.live_indices || []).join(', '); });

    const d = out?.derived || {};
    const mismatch = Math.abs((d.bills_sum || 0) - (d.total_bills || 0)) > (d.total_bills || 1) * 1e-6 + 1;

    mount(
        card('Bills of quantities',
            h('p', { class: 'dim small' }, 'BOQ value drives the physical weight Wi = value / total. Live indices (comma-separated codes) are the cost elements that escalate this bill under the Multiple formula; blank means all.'),
            h('div', { class: 'ipc-scroll' }, h('table', { class: 'list ipc-grid' },
                h('thead', {}, h('tr', {},
                    h('th', {}, 'Code'), h('th', {}, 'Name'), h('th', { class: 'num' }, 'BOQ value'),
                    h('th', { class: 'num' }, 'Weight'), h('th', {}, 'Live indices'), h('th', { class: 'center' }, 'Non-adj.'), h('th', {}))),
                h('tbody', {}, ...rows),
            )),
            h('div', { class: 'ipc-rowbar' },
                h('div', { class: mismatch ? 'lamp bad' : 'lamp ok' },
                    'Σ bills ' + money0(d.bills_sum) + (mismatch ? ' ≠ total ' + money0(d.total_bills) : ' = total')),
                C.canEdit ? h('button', { class: 'btn small', onclick: addBill }, 'Add bill') : null,
            ),
        ),
    );
}

function addBill() {
    project.contract.bills.push({ code: '', name: '', boq_value: 0, live_indices: [], live_indices_str: '', non_adjustable: false });
    edited();
}

function indices() {
    const r = project.indices; // regime
    const portionDtos = out?.portions || [];
    const psum = r.portions.reduce((s, p) => s + (p.proportion || 0), 0);

    const blocks = r.portions.map((p, pi) => {
        const dto = portionDtos[pi] || {};
        const elRows = p.elements.map((e, ei) => h('tr', {},
            h('td', {}, inlineText(e, 'code')),
            h('td', {}, inlineText(e, 'description', 'wide')),
            h('td', {}, inlineText(e, 'letter')),
            h('td', { class: 'num' }, inlineNum(e, 'coefficient')),
            h('td', { class: 'num' }, inlineNum(e, 'base_value')),
            h('td', {}, C.canEdit ? h('button', { class: 'btn tiny ghost', onclick: () => { p.elements.splice(ei, 1); edited(); } }, '×') : null),
        ));
        const cs = dto.coefficient_sum ?? 0;
        return card(null,
            h('div', { class: 'ipc-form' },
                field('Label', p, 'label', { type: 'text' }),
                field('Currency', p, 'currency', { type: 'text' }),
                field('Proportion', p, 'proportion'),
                field('Fixed coefficient a', p, 'fixed_coefficient'),
                field('Exchange rate', p, 'exchange_rate'),
                field('Index source', p, 'index_source', { type: 'text' }),
                field('Base date', p, 'base_date', { type: 'text' }),
            ),
            h('p', { class: 'ipc-formula' }, dto.formula_symbolic || ''),
            h('table', { class: 'list ipc-grid' },
                h('thead', {}, h('tr', {}, h('th', {}, 'Code'), h('th', {}, 'Description'), h('th', {}, 'Letter'),
                    h('th', { class: 'num' }, 'Coefficient'), h('th', { class: 'num' }, 'Base value'), h('th', {}))),
                h('tbody', {}, ...elRows)),
            h('div', { class: 'ipc-rowbar' },
                h('span', { class: (Math.abs(cs - 1) < 1e-6 ? 'lamp ok' : 'lamp bad') }, 'a + Σb = ' + num(cs, 4)),
                C.canEdit ? h('button', { class: 'btn small', onclick: () => { p.elements.push({ code: '', description: '', coefficient: 0, base_value: 0, letter: '' }); edited(); } }, 'Add element') : null,
            ),
        );
    });

    mount(
        card('Clause 13.8 index regime',
            h('p', { class: 'dim small' }, 'Each currency portion’s coefficients (a + Σb) must sum to 1, and the portions themselves must sum to 1.'),
            h('span', { class: (Math.abs(psum - 1) < 1e-6 ? 'lamp ok' : 'lamp bad') }, 'Σ proportions = ' + num(psum, 4)),
        ),
        ...blocks,
    );
}

function periods() {
    const ps = project.period || (project.period = []);
    if (!ps.length) {
        mount(card('Periods', h('p', { class: 'dim' }, 'No periods yet.'),
            C.canEdit ? h('button', { class: 'btn', onclick: addPeriod }, 'Add first period') : null));
        return;
    }

    const list = ps.map((p, i) => {
        const cert = (out?.variants?.single || [])[i];
        return h('details', { class: 'ipc-period', ...(i === ps.length - 1 ? { open: true } : {}) },
            h('summary', {}, h('strong', {}, 'IPC ' + p.number), ' · ', inlineText(p, 'month', 'wide'),
                cert ? h('span', { class: 'dim small', style: 'margin-left:auto' }, pct(cert.physical_progress) + ' · net ' + money0(cert.net_payable)) : null),
            periodBody(p, i),
        );
    });

    mount(
        card('Periods',
            h('p', { class: 'dim small' }, 'One block per month. A certificate is a fold over this ordered list — every cumulative figure is recomputed, never carried from a stored cell.'),
            C.canEdit ? h('div', { class: 'ipc-rowbar' }, h('button', { class: 'btn small', onclick: addPeriod }, 'Add period')) : null,
        ),
        ...list,
    );
}

function periodBody(p, idx) {
    // Bill entries
    const entryRows = p.entries.map(e => h('tr', {},
        h('td', {}, e.code),
        h('td', { class: 'num' }, inlineNum(e, 'measured')),
        h('td', { class: 'num' }, inlinePctInput(e, 'progress')),
        h('td', { class: 'num' }, inlineNum(e, 'materials_on_site')),
        h('td', { class: 'num' }, inlineNum(e, 'provisional_sum')),
    ));

    // Index values for this period
    const codes = new Set();
    project.indices.portions.forEach(pt => pt.elements.forEach(e => codes.add(e.code)));
    if (!p.indices) p.indices = {};
    const idxRows = [...codes].map(code => h('label', { class: 'ipc-f' },
        h('span', {}, code),
        (() => {
            const inp = h('input', { type: 'number', step: 'any', value: p.indices[code] ?? '', disabled: !C.canEdit });
            inp.addEventListener('change', () => { const v = parseFloat(inp.value); if (isNaN(v)) delete p.indices[code]; else p.indices[code] = v; edited(); });
            return inp;
        })(),
    ));

    return h('div', { class: 'ipc-period-body' },
        h('div', { class: 'ipc-scroll' }, h('table', { class: 'list ipc-grid' },
            h('thead', {}, h('tr', {}, h('th', {}, 'Bill'), h('th', { class: 'num' }, 'Measured'),
                h('th', { class: 'num' }, 'Progress %'), h('th', { class: 'num' }, 'Materials on site'), h('th', { class: 'num' }, 'Prov. sum'))),
            h('tbody', {}, ...entryRows))),
        h('h3', {}, 'Published indices'),
        h('div', { class: 'ipc-form tight' }, ...idxRows),
        h('div', { class: 'ipc-form' },
            field('Planned progress (0–1)', p, 'planned_progress'),
            field('Actual cost to date', p, 'actual_cost'),
        ),
        C.canEdit ? h('div', { class: 'ipc-rowbar' }, h('button', { class: 'btn tiny ghost', onclick: () => { project.period.splice(idx, 1); edited(); } }, 'Delete IPC ' + p.number)) : null,
    );
}

function addPeriod() {
    const ps = project.period || (project.period = []);
    const prev = ps[ps.length - 1];
    const num = prev ? prev.number + 1 : 1;
    const entries = project.contract.bills.map(b => {
        const pe = prev?.entries?.find(x => x.code === b.code);
        return { code: b.code, measured: 0, progress: pe?.progress ?? 0, materials_on_site: 0, nominated_sub: 0, provisional_sum: 0 };
    });
    ps.push({
        number: num, month: '', entries,
        indices: prev ? { ...prev.indices } : {}, correction: {},
        planned_progress: null, actual_cost: null,
        contingency_released: 0, change_in_law: 0, employer_claims: 0,
        contractor_claims: 0, delay_interest: 0, other_taxes: 0,
    });
    edited();
}

function certificate() {
    const ps = project.period || [];
    if (!ps.length) { mount(card('Certificate', h('p', { class: 'dim' }, 'No periods to certify.'))); return; }
    certPeriod = Math.min(certPeriod, ps.length - 1);

    const picker = h('select', { onchange: e => { certPeriod = +e.target.value; render(); } },
        ...ps.map((p, i) => h('option', { value: i, ...(i === certPeriod ? { selected: true } : {}) }, 'IPC ' + p.number + ' — ' + (p.month || ''))));

    const variants = [['traditional', 'Traditional'], ['single', 'Single'], ['multiple', 'Multiple']];
    const cols = variants.map(([k]) => (out?.variants?.[k] || [])[certPeriod]);

    const line = (label, pick, strong) => h('tr', { class: strong ? 'strong' : '' },
        h('td', {}, label), ...cols.map(c => h('td', { class: 'num' }, c ? pick(c) : '—')));

    const t = cols[0], s = cols[1];
    const agree = t && s && Math.abs(t.price_adjustment - s.price_adjustment) / Math.max(1, Math.abs(t.price_adjustment)) < 1e-6;

    mount(
        card(null, h('div', { class: 'ipc-rowbar' }, h('strong', {}, 'Certificate for '), picker,
            h('span', { class: (agree ? 'lamp ok' : 'lamp bad'), style: 'margin-left:auto' }, agree ? 'Traditional = Single ✓' : 'Traditional ≠ Single'))),
        card(null, h('div', { class: 'ipc-scroll' }, h('table', { class: 'list ipc-cert' },
            h('thead', {}, h('tr', {}, h('th', {}, 'Item'), ...variants.map(([, l]) => h('th', { class: 'num' }, l)))),
            h('tbody', {},
                line('Measured works', c => money(c.measured_works)),
                line('Net adjustable amount', c => money(c.net_adjustable)),
                line('Adjustment factor', c => num(c.adjustment_factor, 6)),
                line('Price adjustment', c => money(c.price_adjustment), true),
                line('Subtotal of works', c => money(c.subtotal_works)),
                line('Retention this IPC', c => money(c.retention)),
                line('Cumulative retention', c => money(c.cumulative_retention)),
                line('Subtotal after deductions', c => money(c.subtotal_after_deductions)),
                line('VAT', c => money(c.vat)),
                line('Gross', c => money(c.gross)),
                line('Advance recovery', c => money(c.advance_recovery)),
                line('Net payable', c => money(c.net_payable), true),
                line('Certified to date', c => money(c.certified_to_date)),
                line('Physical progress', c => pct(c.physical_progress)),
            ),
        ))),
    );
}

function formula() {
    const ps = project.period || [];
    if (!ps.length) { mount(card('Formula', h('p', { class: 'dim' }, 'Add a period to evaluate the formula.'))); return; }
    formulaPeriod = Math.min(formulaPeriod, ps.length - 1);

    let res;
    try { res = JSON.parse(engine.formula(JSON.stringify(project), formulaPeriod)); }
    catch (e) { res = { error: String(e) }; }

    const picker = h('select', { onchange: e => { formulaPeriod = +e.target.value; render(); } },
        ...ps.map((p, i) => h('option', { value: i, ...(i === formulaPeriod ? { selected: true } : {}) }, 'IPC ' + p.number + ' — ' + (p.month || ''))));

    mount(card(null, h('div', { class: 'ipc-rowbar' }, h('strong', {}, 'Clause 13.8 for '), picker)));

    if (res.error) { mount(card('Error', h('p', { class: 'bad' }, res.error))); return; }

    (res.portions || []).forEach(p => {
        const termRows = (p.terms || []).map(t => h('tr', { class: t.frozen ? 'frozen' : '' },
            h('td', {}, t.letter), h('td', {}, t.code), h('td', {}, t.description),
            h('td', { class: 'num' }, num(t.coefficient, 4)),
            h('td', { class: 'num' }, num(t.base, 2)),
            h('td', { class: 'num' }, num(t.current, 2) + (t.frozen ? ' (frozen)' : '')),
            h('td', { class: 'num' }, num(t.movement, 6)),
            h('td', { class: 'num' }, num(t.contribution, 6)),
        ));
        mount(card(p.label + ' — ' + p.symbol,
            h('p', { class: 'ipc-formula' }, p.formula_symbolic),
            h('p', { class: 'ipc-formula num' }, p.formula_numeric),
            h('div', { class: 'ipc-scroll' }, h('table', { class: 'list ipc-grid' },
                h('thead', {}, h('tr', {}, h('th', {}, 'Letter'), h('th', {}, 'Code'), h('th', {}, 'Description'),
                    h('th', { class: 'num' }, 'Coeff.'), h('th', { class: 'num' }, 'Base'), h('th', { class: 'num' }, 'Current'),
                    h('th', { class: 'num' }, 'Movement'), h('th', { class: 'num' }, 'Contribution'))),
                h('tbody', {}, ...termRows))),
            h('p', { class: 'dim small' }, 'a = ' + num(p.fixed_coefficient, 4) + ' · increment = ' + num(p.increment, 6) + ' · multiplier Pn = ' + num(p.multiplier, 6)),
        ));
    });
}

function exportView() {
    const errs = out?.errors || [];
    const ok = out?.valid;
    mount(
        card('Export',
            h('p', { class: 'dim small' }, 'Validation must pass before export. The workbook carries the figures exactly as the engine computed them.'),
            h('div', { class: ok ? 'lamp ok' : 'lamp bad' }, ok ? 'Validation passed' : errs.length + ' problem(s) — fix in the tabs above'),
            errs.length ? h('ul', { class: 'ipc-errs' }, ...errs.map(e => h('li', {}, e))) : null,
            h('div', { class: 'ipc-rowbar', style: 'margin-top:14px' },
                C.canExport
                    ? h('button', { class: 'btn', disabled: !ok || !out.period_count, onclick: doExport }, 'Export workbook (XLSX)')
                    : h('span', { class: 'dim' }, 'You do not have export permission in this module.'),
            ),
        ),
    );
}

async function doExport() {
    setStatus('Building workbook…');
    try {
        const res = await fetch(C.urls.export, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrf },
            body: JSON.stringify({ variants: out.variants, currency: CUR }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'IPC-' + (project.contract.project_name || 'project').replace(/[^A-Za-z0-9_-]+/g, '_') + '.xlsx';
        a.click();
        URL.revokeObjectURL(a.href);
        setStatus('Workbook downloaded');
    } catch (e) {
        setStatus('Export failed: ' + e.message);
    }
}

// ---- inline editors -------------------------------------------------------

function inlineText(obj, key, cls = '', after) {
    if (obj[key] == null) obj[key] = '';
    const inp = h('input', { type: 'text', class: 'inline ' + cls, value: obj[key], disabled: !C.canEdit });
    inp.addEventListener('change', () => { obj[key] = inp.value; if (after) after(); edited(); });
    return inp;
}
function inlineNum(obj, key) {
    const inp = h('input', { type: 'number', step: 'any', class: 'inline num', value: obj[key] ?? 0, disabled: !C.canEdit });
    inp.addEventListener('change', () => { obj[key] = parseFloat(inp.value || '0'); edited(); });
    return inp;
}
// progress stored as fraction 0..1 but edited as a percentage
function inlinePctInput(obj, key) {
    const inp = h('input', { type: 'number', step: 'any', class: 'inline num', value: ((obj[key] ?? 0) * 100), disabled: !C.canEdit });
    inp.addEventListener('change', () => { obj[key] = (parseFloat(inp.value || '0')) / 100; edited(); });
    return inp;
}
function checkbox(obj, key) {
    const inp = h('input', { type: 'checkbox', disabled: !C.canEdit });
    inp.checked = !!obj[key];
    inp.addEventListener('change', () => { obj[key] = inp.checked; edited(); });
    return inp;
}

// ---- boot -----------------------------------------------------------------

function wireTabs() {
    document.getElementById('ipc-tabs').addEventListener('click', e => {
        const b = e.target.closest('button[data-tab]');
        if (!b) return;
        tab = b.dataset.tab;
        document.querySelectorAll('#ipc-tabs button').forEach(x => x.classList.toggle('on', x === b));
        render();
    });
    if (saveBtn) saveBtn.addEventListener('click', save);
}

async function boot() {
    try {
        const mod = await import('./engine.js');
        engine = mod.default;
    } catch (e) {
        setStatus('Engine failed to load: ' + e.message);
        view.innerHTML = '<div class="card bad">The native IPC engine could not be loaded.</div>';
        return;
    }

    if (C.data && C.data.contract) {
        project = C.data;
    } else {
        // Fresh project: the engine writes the opening contract for the agency,
        // and we save it straight back so the row is never left half-built.
        project = JSON.parse(engine.preset(C.agency));
        if (C.name) project.contract.project_name = C.name;
        if (C.contract_no) project.contract.contract_no = C.contract_no;
        dirty = true;
        await save();
    }

    recompute();
    wireTabs();
    render();
    setStatus(C.canEdit ? 'Ready' : 'Read-only');
    if (saveBtn) saveBtn.disabled = !dirty;
}

boot();
