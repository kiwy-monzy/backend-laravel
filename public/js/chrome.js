/**
 * The chrome's behaviour: collapse, the hover tooltip, and global search.
 *
 * Ported from `tauri-plugin-sidebar/src/js/sidebar.js` and
 * `tauri-plugin-decorum/src/js/titlebar.js`. The desktop versions build their
 * DOM in JavaScript because they are injected into a page they do not own;
 * here Blade renders the markup and this file only wires it, which is both
 * less code and one fewer thing that can flash on load.
 */
(function () {
    "use strict";

    const body = document.body;
    const sb = document.getElementById("sb");
    if (!sb) return;

    const MOBILE = () => window.matchMedia("(max-width: 860px)").matches;

    /* ── Collapse ─────────────────────────────────────────────────────────── */

    // Persisted, because a rail that forgets is a rail you re-collapse on every
    // page load. The state lives in one place — an attribute on <html> — which
    // an inline script in the layout sets before the first paint; this only has
    // to handle the toggle.
    const root = document.documentElement;
    function setCollapsed(on) {
        if (on) root.dataset.sbCollapsed = "1";
        else delete root.dataset.sbCollapsed;
        try { localStorage.setItem("fge_sb_collapsed", on ? "1" : "0"); } catch (e) { /* private mode */ }
    }

    const toggle = document.getElementById("sb-toggle");
    if (toggle) {
        toggle.addEventListener("click", () => {
            if (MOBILE()) {
                body.classList.toggle("sb-open");
            } else {
                setCollapsed(document.documentElement.dataset.sbCollapsed !== "1");
            }
        });
    }

    const scrim = document.querySelector(".sb-scrim");
    if (scrim) scrim.addEventListener("click", () => body.classList.remove("sb-open"));

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") body.classList.remove("sb-open");
    });

    /* ── Hover tooltip ────────────────────────────────────────────────────── */

    // On <body> rather than inside the rail: the rail clips its own overflow,
    // so a tooltip parented to a nav item was invisible exactly when it was
    // needed.
    const tip = document.createElement("div");
    tip.id = "sb-tip";
    body.appendChild(tip);

    sb.querySelectorAll("[data-tip]").forEach((el) => {
        el.addEventListener("mouseenter", () => {
            if (document.documentElement.dataset.sbCollapsed !== "1" || MOBILE()) return;
            const r = el.getBoundingClientRect();
            tip.textContent = el.dataset.tip;
            tip.style.left = r.right + 8 + "px";
            tip.style.top = r.top + r.height / 2 + "px";
            tip.style.opacity = "1";
        });
        el.addEventListener("mouseleave", () => { tip.style.opacity = "0"; });
    });

    /* ── Global search ────────────────────────────────────────────────────── */

    /**
     * Searches the site's own data — messages, donations, volunteers, users,
     * files — through `/admin/search`, which applies the same website scoping
     * as every other page. An admin searching for a donor on another website
     * gets nothing, not a redacted row.
     */
    const wrap = document.getElementById("tb-search");
    if (!wrap) return;

    const input = wrap.querySelector("input");
    const clear = wrap.querySelector(".tb-clear");
    const results = wrap.querySelector(".tb-results");
    let timer = null;
    let seq = 0;

    const esc = (s) => {
        const d = document.createElement("div");
        d.textContent = s == null ? "" : String(s);
        return d.innerHTML;
    };

    function render(hits) {
        results.innerHTML = "";
        if (!hits || !hits.length) {
            const empty = document.createElement("div");
            empty.className = "tb-empty";
            empty.textContent = input.dataset.empty || "No results";
            results.appendChild(empty);
            results.style.display = "block";
            return;
        }

        const head = document.createElement("div");
        head.className = "tb-head";
        head.textContent = hits.length + (hits.length === 1 ? " result" : " results");
        results.appendChild(head);

        hits.forEach((h) => {
            const a = document.createElement("a");
            a.className = "tb-result";
            a.href = h.href;
            a.innerHTML =
                '<div class="row">' +
                '<span class="tb-kind">' + esc(h.kind) + "</span>" +
                '<span class="tb-title">' + esc(h.title) + "</span>" +
                "</div>" +
                (h.snippet ? '<div class="tb-snip">' + esc(h.snippet) + "</div>" : "");
            results.appendChild(a);
        });
        results.style.display = "block";
    }

    input.addEventListener("input", () => {
        const q = input.value.trim();
        clear.style.display = input.value ? "flex" : "none";
        if (timer) clearTimeout(timer);
        if (q.length < 2) { results.style.display = "none"; results.innerHTML = ""; return; }

        timer = setTimeout(() => {
            // A response that arrives after a newer one must not overwrite it.
            const mine = ++seq;
            fetch("/admin/search?q=" + encodeURIComponent(q), { headers: { Accept: "application/json" } })
                .then((r) => (r.ok ? r.json() : []))
                .then((hits) => { if (mine === seq) render(hits); })
                .catch(() => { if (mine === seq) render([]); });
        }, 220);
    });

    clear.addEventListener("click", () => {
        input.value = "";
        clear.style.display = "none";
        results.style.display = "none";
        results.innerHTML = "";
        input.focus();
    });

    input.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            input.value = "";
            clear.style.display = "none";
            results.style.display = "none";
        }
        if (e.key === "Enter") {
            const first = results.querySelector(".tb-result");
            if (first) { e.preventDefault(); window.location = first.href; }
        }
    });

    document.addEventListener("mousedown", (e) => {
        if (!wrap.contains(e.target)) results.style.display = "none";
    });

    // The desktop app binds Ctrl+K to the same box; keeping it means muscle
    // memory carries between the two.
    document.addEventListener("keydown", (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === "k") {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();

/**
 * Keep the sidebar's scroll position across navigations.
 *
 * The rail is a full-page reload away from every other page, so a long module
 * list scrolled to "Purchasing" snapped back to the top on every click — you
 * lost your place precisely when you were working through the list. The offset
 * is written on unload and restored before paint, and the active item is
 * scrolled into view when it would otherwise be off-screen.
 */
(function () {
    "use strict";

    var rail = document.querySelector(".sb-scroll");
    if (!rail) return;

    var KEY = "fge_sb_scroll";

    try {
        var saved = parseInt(sessionStorage.getItem(KEY) || "0", 10);
        if (saved > 0) rail.scrollTop = saved;
    } catch (e) { /* private mode: start at the top */ }

    // `pagehide` rather than `unload`: the latter is ignored by the bfcache and
    // is on its way out of the platform.
    window.addEventListener("pagehide", function () {
        try { sessionStorage.setItem(KEY, String(rail.scrollTop)); } catch (e) {}
    });

    // A restored offset can still leave the current page's item out of sight —
    // when it does, the active item wins.
    var active = rail.querySelector(".sb-item.on");
    if (active) {
        var itemTop = active.offsetTop;
        var itemBottom = itemTop + active.offsetHeight;
        if (itemTop < rail.scrollTop || itemBottom > rail.scrollTop + rail.clientHeight) {
            rail.scrollTop = Math.max(0, itemTop - rail.clientHeight / 2 + active.offsetHeight / 2);
        }
    }

    // Same for the sub-rail, which gets long in modules with many sections.
    var sub = document.querySelector(".sb2");
    var subActive = sub && sub.querySelector(".sb2-item.on");
    if (sub && subActive) {
        var t = subActive.offsetTop;
        if (t < sub.scrollTop || t + subActive.offsetHeight > sub.scrollTop + sub.clientHeight) {
            sub.scrollTop = Math.max(0, t - sub.clientHeight / 2);
        }
    }
})();
