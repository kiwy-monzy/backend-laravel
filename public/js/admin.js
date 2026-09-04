/**
 * Admin-only behaviour: live JSON validation and delete confirmations.
 *
 * Small enough to stay one file, and deliberately unconditional — every page
 * loads it and each block no-ops when its element is absent, which is cheaper
 * than a per-page bundle for a codebase with no build step.
 */
(function () {
    "use strict";

    /* ── The content section editor ───────────────────────────────────────── */

    // The server validates too. This is here because pasting a section's JSON
    // and only finding out it was malformed after a round trip — with the
    // textarea repopulated from `old()` — is how people lose an afternoon.
    document.querySelectorAll("textarea.json-editor").forEach(function (ta) {
        const status = document.querySelector(ta.dataset.status || "#json-status");
        const submit = ta.form ? ta.form.querySelector("[type=submit]") : null;

        function check() {
            if (!status) return;
            if (!ta.value.trim()) {
                status.textContent = "";
                status.className = "json-status";
                if (submit) submit.disabled = false;
                return;
            }
            try {
                JSON.parse(ta.value);
                status.textContent = ta.dataset.okText || "Valid JSON";
                status.className = "json-status ok";
                if (submit) submit.disabled = false;
            } catch (e) {
                status.textContent = e.message;
                status.className = "json-status bad";
                if (submit) submit.disabled = true;
            }
        }

        ta.addEventListener("input", check);
        check();

        // Tab indents rather than leaving the field: this is an editor, and
        // losing focus mid-object is the single most annoying thing it could do.
        ta.addEventListener("keydown", function (e) {
            if (e.key !== "Tab") return;
            e.preventDefault();
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            ta.value = ta.value.slice(0, start) + "  " + ta.value.slice(end);
            ta.selectionStart = ta.selectionEnd = start + 2;
        });

        const pretty = document.querySelector("[data-json-format]");
        if (pretty) {
            pretty.addEventListener("click", function () {
                try {
                    ta.value = JSON.stringify(JSON.parse(ta.value), null, 2);
                    check();
                } catch (e) { /* check() already says why */ }
            });
        }
    });

    /* ── Destructive buttons ──────────────────────────────────────────────── */

    // A delete here removes a donor record or somebody's account; a native
    // confirm is crude but it is the difference between a slip and an incident.
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    /* ── File pickers ─────────────────────────────────────────────────────── */

    document.querySelectorAll("input[type=file][data-auto-submit]").forEach(function (input) {
        input.addEventListener("change", function () {
            if (input.files && input.files.length && input.form) input.form.submit();
        });
    });
})();

/**
 * The content editor: repeaters, the image picker and the preview pane.
 *
 * Appended to admin.js rather than shipped as its own file because there is no
 * build step and one more <script> tag on every page costs more than the
 * bytes save.
 */
(function () {
    "use strict";

    /* ── Repeaters ────────────────────────────────────────────────────────── */

    document.querySelectorAll("fieldset.repeat[data-repeat]").forEach(function (group) {
        const rows = group.querySelector(".repeat-rows");
        const template = group.querySelector(".repeat-template");
        const add = group.querySelector(".repeat-add");
        if (!rows || !template || !add) return;

        add.addEventListener("click", function () {
            // Keep counting up rather than using the current row count: two
            // rows would otherwise collide after one in the middle is removed.
            const index = parseInt(add.dataset.next || "0", 10);
            add.dataset.next = index + 1;

            const html = template.innerHTML.replace(/__i__/g, index);
            const holder = document.createElement("div");
            holder.innerHTML = html.trim();
            rows.appendChild(holder.firstElementChild);
        });

        group.addEventListener("click", function (e) {
            if (!e.target.classList.contains("repeat-remove")) return;
            e.target.closest(".repeat-row")?.remove();
        });
    });

    /* ── Image picker ─────────────────────────────────────────────────────── */

    const picker = document.getElementById("image-picker");
    let pickerTarget = null;

    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("image-pick")) {
            pickerTarget = e.target.closest(".image-row")?.querySelector(".image-input");
            picker?.showModal();
            return;
        }

        const tile = e.target.closest(".picker-tile");
        if (tile && pickerTarget) {
            pickerTarget.value = tile.dataset.url;
            pickerTarget.dispatchEvent(new Event("input", { bubbles: true }));
            picker?.close();
        }
    });

    // The thumbnail follows the path, whether it was picked or typed.
    document.addEventListener("input", function (e) {
        if (!e.target.classList.contains("image-input")) return;
        const thumb = e.target.closest(".image-row")?.querySelector(".image-thumb");
        if (!thumb) return;
        thumb.src = e.target.value;
        thumb.style.display = e.target.value ? "" : "none";
    });

    // The colour swatch and its hex box are two views of one value.
    document.addEventListener("input", function (e) {
        const row = e.target.closest(".image-row");
        if (!row) return;
        const swatch = row.querySelector(".color-swatch");
        const text = row.querySelector(".color-text");
        if (!swatch || !text) return;

        if (e.target === swatch) text.value = swatch.value;
        else if (e.target === text && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(text.value)) swatch.value = text.value;
    });

    /* ── Preview pane ─────────────────────────────────────────────────────── */

    const frame = document.getElementById("content-preview");
    if (!frame) return;

    document.getElementById("preview-reload")?.addEventListener("click", function () {
        // Reassigning src rather than calling reload() avoids needing
        // same-origin access to the frame's document.
        frame.src = frame.src;
    });

    document.querySelectorAll("[data-preview-width]").forEach(function (button) {
        button.addEventListener("click", function () {
            const width = parseInt(button.dataset.previewWidth, 10);
            frame.style.width = width ? width + "px" : "100%";
        });
    });

    // After a save the page reloads and the iframe would come back from cache
    // showing the old content, which reads as "my edit did not save".
    if (document.querySelector(".flash.ok")) {
        const url = new URL(frame.src, window.location.origin);
        url.searchParams.set("_", Date.now());
        frame.src = url.toString();
    }
})();

/**
 * The image picker's contents, fetched from the Storage module.
 *
 * Loaded on demand rather than rendered into every content page: the library
 * is per organization and can run to hundreds of files, and inlining it made
 * the editor's HTML grow with the charity's photo album.
 */
(function () {
    "use strict";

    const picker = document.getElementById("image-picker");
    if (!picker || !picker.dataset.src) return;

    const grid = document.getElementById("picker-grid");
    const select = document.getElementById("picker-collection");
    const search = document.getElementById("picker-search");
    let timer = null;
    let loaded = false;

    function render(payload) {
        if (select && !select.options.length) {
            select.innerHTML = '<option value="">All collections</option>';
            payload.collections.forEach(function (c) {
                const o = document.createElement("option");
                o.value = c.slug;
                o.textContent = c.name;
                select.appendChild(o);
            });
        }

        grid.innerHTML = "";

        if (!payload.files.length) {
            const empty = document.createElement("p");
            empty.className = "dim small";
            empty.style.padding = "12px";
            // Naming the collection matters: "nothing here" reads as a bug
            // when the files are simply in a folder you have not selected.
            empty.textContent = "No images in this collection yet.";
            grid.appendChild(empty);
            return;
        }

        payload.files.forEach(function (f) {
            const tile = document.createElement("button");
            tile.type = "button";
            tile.className = "tile picker-tile";
            tile.dataset.url = f.url;

            const img = document.createElement("img");
            img.src = f.url;
            img.alt = f.filename;
            img.loading = "lazy";

            const body = document.createElement("div");
            body.className = "tile-body";
            const name = document.createElement("div");
            name.className = "name";
            name.textContent = f.filename;
            body.appendChild(name);

            tile.appendChild(img);
            tile.appendChild(body);
            grid.appendChild(tile);
        });
    }

    function load() {
        const url = new URL(picker.dataset.src, window.location.origin);
        if (select && select.value) url.searchParams.set("collection", select.value);
        if (search && search.value) url.searchParams.set("q", search.value);

        fetch(url, { headers: { Accept: "application/json" } })
            .then(function (r) { return r.ok ? r.json() : { collections: [], files: [] }; })
            .then(render)
            .catch(function () {
                grid.innerHTML = '<p class="dim small" style="padding:12px">Could not load storage.</p>';
            });
    }

    // The first open pays for the fetch; reopening reuses what is there.
    picker.addEventListener("click", function () {
        if (!loaded) { loaded = true; load(); }
    }, true);

    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("image-pick") && !loaded) { loaded = true; load(); }
    });

    select?.addEventListener("change", load);
    search?.addEventListener("input", function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(load, 220);
    });

    const uploadForm = document.getElementById("picker-upload-form");
    const uploadInput = document.getElementById("picker-upload-input");
    const uploadStatus = document.getElementById("picker-upload-status");
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

    uploadForm?.addEventListener("submit", async function (e) {
        e.preventDefault();
        if (!uploadInput || !uploadInput.files.length) return;
        const col = select?.value || "website";
        const template = picker.dataset.upload || "";
        const url = template.replace("__COLLECTION__", col);
        const fd = new FormData();
        for (const f of uploadInput.files) fd.append("files[]", f);
        if (uploadStatus) uploadStatus.textContent = "Uploading…";
        try {
            const res = await fetch(url, { method: "POST", headers: { "X-CSRF-TOKEN": csrf }, body: fd });
            if (!res.ok) throw new Error("Upload failed");
            uploadInput.value = "";
            if (uploadStatus) uploadStatus.textContent = "Uploaded";
            load();
            setTimeout(function () { if (uploadStatus) uploadStatus.textContent = ""; }, 2000);
        } catch (err) {
            if (uploadStatus) uploadStatus.textContent = "Upload failed";
        }
    });
})();
