/**
 * Dashboard widgets, ported from the React dashboard package to vanilla JS.
 *
 * **Ported rather than imported.** This app has no build step on purpose —
 * `composer install` is the whole setup — and pulling in React, a bundler and a
 * watcher to render three widgets would change that for everybody who ever
 * deploys it. The behaviour is the same; the mechanism is a class per widget
 * that finds its own root by data attribute.
 */
(function () {
    "use strict";

    /* ── Holographic card ─────────────────────────────────────────────────── */

    /**
     * Tilt toward the pointer, flip on click, shine follows the cursor.
     *
     * The rotation is deliberately small (±9°): the original's ±18° reads as a
     * toy on a page that is otherwise a register, and the shine does most of the
     * work anyway.
     */
    function initHolo(root) {
        const inner = root.querySelector("[data-holo-inner]");
        const shine = root.querySelector("[data-holo-shine]");
        let flipped = false;

        const apply = (rx, ry) => {
            inner.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
        };

        root.addEventListener("mousemove", (e) => {
            const r = root.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top) / r.height;
            apply((py - 0.5) * -9, (flipped ? 180 : 0) + (px - 0.5) * 9);
            shine.style.background =
                `radial-gradient(circle at ${px * 100}% ${py * 100}%,` +
                " rgba(255,255,255,.20) 0%, rgba(255,255,255,.08) 40%, transparent 70%)";
        });

        root.addEventListener("mouseleave", () => {
            apply(0, flipped ? 180 : 0);
            shine.style.background =
                "radial-gradient(circle at 50% 50%, rgba(255,255,255,.18) 0%," +
                " rgba(255,255,255,.08) 40%, transparent 70%)";
        });

        root.addEventListener("click", () => {
            flipped = !flipped;
            apply(0, flipped ? 180 : 0);
        });

        // The barcode: decorative, but generated rather than hand-written so it
        // is not eighteen copies of the same markup.
        root.querySelectorAll("[data-holo-bars]").forEach((host) => {
            const tall = host.dataset.holoBars === "tall";
            for (let i = 0; i < 18; i++) {
                const bar = document.createElement("i");
                bar.style.width = (i % 3 === 0 ? 4 : 2) + "px";
                bar.style.height = (tall ? 28 : 22) + "px";
                bar.style.opacity = i % 4 === 0 ? "0.7" : "1";
                host.appendChild(bar);
            }
        });
    }

    /* ── Calendar ─────────────────────────────────────────────────────────── */

    /**
     * The month grid, drawn into the original's SVG.
     *
     * Geometry is the React component's own: cells on a 67 × 56 pitch, 54 × 51
     * rounded rects with rx 15, the sixth row nudged 10px, today amber, the
     * event dot at (42, 10) radius 5. Emitting them here rather than in React
     * is the only difference — this app has no build step.
     */
    function initCalendar(root) {
        const cells = root.querySelector("[data-cal-cells]");
        const monthText = root.querySelector("[data-cal-month]");
        const yearText = root.querySelector("[data-cal-year]");
        const events = JSON.parse(root.dataset.events || "{}");
        const listUrl = root.dataset.listUrl || "";
        const NS = "http://www.w3.org/2000/svg";

        const today = new Date();
        let year = today.getFullYear();
        let month = today.getMonth();

        const key = (d) =>
            d.getFullYear() + "-" +
            String(d.getMonth() + 1).padStart(2, "0") + "-" +
            String(d.getDate()).padStart(2, "0");

        const el = (name, attrs) => {
            const n = document.createElementNS(NS, name);
            for (const k in attrs) n.setAttribute(k, attrs[k]);
            return n;
        };

        function render() {
            const label = new Date(year, month, 1)
                .toLocaleString("default", { month: "long" })
                .toUpperCase();
            monthText.textContent = label;
            yearText.textContent = ", " + year;

            while (cells.firstChild) cells.removeChild(cells.firstChild);

            const first = new Date(year, month, 1);
            // Monday-first, as in the original.
            const offset = (first.getDay() + 6) % 7;
            const inMonth = new Date(year, month + 1, 0).getDate();
            const prevDays = new Date(year, month, 0).getDate();

            for (let i = 0; i < 42; i++) {
                const rel = i - offset + 1;
                let date, day, inCurrent = true;
                if (rel <= 0) {
                    day = prevDays + rel;
                    date = new Date(year, month - 1, day);
                    inCurrent = false;
                } else if (rel > inMonth) {
                    day = rel - inMonth;
                    date = new Date(year, month + 1, day);
                    inCurrent = false;
                } else {
                    day = rel;
                    date = new Date(year, month, day);
                }

                const col = i % 7;
                const row = Math.floor(i / 7);
                const x = col * 67;
                const y = row * 56 + 220 + (row === 5 ? 10 : 0);
                const isToday = date.toDateString() === today.toDateString();
                const n = events[key(date)];

                const g = el("g", { transform: `translate(${x} ${y})` });

                if (n && listUrl) {
                    const a = el("a", { href: listUrl + "?on=" + key(date), class: "cal-linked" });
                    a.appendChild(g);
                    cells.appendChild(a);
                } else {
                    cells.appendChild(g);
                }

                g.appendChild(el("rect", {
                    width: 54, height: 51, rx: 15,
                    fill: isToday ? "#ffab2e" : "#90cf81",
                    stroke: "#707070", "stroke-width": 1,
                    opacity: inCurrent ? 1 : 0.35,
                }));

                const t = el("text", {
                    x: 27, y: 26, fill: "#1a1a1a", "font-size": 28,
                    "text-anchor": "middle", "dominant-baseline": "middle",
                    "font-family": "'Luckiest Guy','luckiest-guy-regular',cursive",
                    opacity: inCurrent ? 1 : 0.5,
                });
                t.textContent = day;
                g.appendChild(t);

                if (n) {
                    g.appendChild(el("circle", {
                        cx: 42, cy: 10, r: 5, fill: "#ae100f",
                        stroke: "#1a1a1a", "stroke-width": 1,
                    }));
                    const title = el("title", {});
                    title.textContent = n + (n === 1 ? " incident" : " incidents");
                    g.appendChild(title);
                }
            }
        }

        root.querySelector("[data-cal-prev]").addEventListener("click", () => {
            if (--month < 0) { month = 11; year--; }
            render();
        });
        root.querySelector("[data-cal-next]").addEventListener("click", () => {
            if (++month > 11) { month = 0; year++; }
            render();
        });

        render();
    }

    /* ── Clock ────────────────────────────────────────────────────────────── */

    /**
     * The analog face from the dashboard package, driven by `setInterval`.
     *
     * The minute marks and hour digits are generated here rather than written
     * out: the original CSS positions 48 marks with 48 `nth-child` rules, which
     * is fine as CSS but would be absurd as markup.
     */
    function initClock(root) {
        const marks = root.querySelector("[data-clock-marks]");
        const digits = root.querySelector("[data-clock-digits]");
        const hour = root.querySelector("[data-clock-hours]");
        const minute = root.querySelector("[data-clock-minutes]");
        const second = root.querySelector("[data-clock-seconds]");
        const readout = root.querySelectorAll("[data-clock-digital] span");

        // 48 marks: every minute except where an hour digit sits.
        for (let i = 1; i <= 59; i++) {
            if (i % 5 === 0) continue;
            const li = document.createElement("li");
            li.style.transform = `rotate(${i * 6}deg) translateY(-12.7em)`;
            marks.appendChild(li);
        }
        for (let i = 1; i <= 12; i++) {
            const li = document.createElement("li");
            li.textContent = i;
            digits.appendChild(li);
        }

        const pad = (n) => String(n).padStart(2, "0");

        // The clock keeps the *app's* timezone, not the visitor's: Laravel
        // writes `config('app.timezone')` on the element, and a formatter for
        // it answers h/m/s in that zone whatever the browser believes. Without
        // the attribute it falls back to local time, as before.
        const tz = root.dataset.timezone || "";
        const tzFormat = tz
            ? new Intl.DateTimeFormat("en-GB", {
                  timeZone: tz,
                  hour: "2-digit",
                  minute: "2-digit",
                  second: "2-digit",
                  hour12: false,
              })
            : null;

        function nowInTz() {
            if (!tzFormat) {
                const t = new Date();
                return [t.getHours(), t.getMinutes(), t.getSeconds()];
            }
            const parts = {};
            for (const p of tzFormat.formatToParts(new Date())) parts[p.type] = p.value;
            // Some engines report midnight as "24" with hour12: false; the
            // modulo brings it back to a clock face and a two-digit readout.
            return [
                parseInt(parts.hour, 10) % 24,
                parseInt(parts.minute, 10),
                parseInt(parts.second, 10),
            ];
        }

        function tick() {
            const [h24, m, s] = nowInTz();
            const h = h24 % 12;

            hour.style.transform = `rotate(${h * 30 + m * 0.5}deg)`;
            minute.style.transform = `rotate(${m * 6}deg)`;
            second.style.transform = `rotate(${s * 6}deg)`;

            if (readout.length === 3) {
                readout[0].textContent = pad(h24);
                readout[1].textContent = pad(m);
                readout[2].textContent = pad(s);
            }
        }

        tick();
        setInterval(tick, 1000);
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-holo]").forEach(initHolo);
        document.querySelectorAll("[data-calendar]").forEach(initCalendar);
        document.querySelectorAll("[data-clock]").forEach(initClock);
    });
})();
