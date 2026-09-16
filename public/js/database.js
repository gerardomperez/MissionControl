/* Database Browser */
(() => {
    const base    = window.BASE_URL || '';
    const api     = `${base}/api/database.php`;
    const PAGE    = 50;

    let currentTable  = null;
    let currentOffset = 0;
    let totalRows     = 0;

    const tableList      = document.getElementById('tableList');
    const dbTableTitle   = document.getElementById('dbTableTitle');
    const dbRowCount     = document.getElementById('dbRowCount');
    const dbSchemaToggle = document.getElementById('dbSchemaToggle');
    const dbSchemaPanel  = document.getElementById('dbSchemaPanel');
    const dbSchemaBody   = document.getElementById('dbSchemaBody');
    const dbEmptyState   = document.getElementById('dbEmptyState');
    const dbTableScroll  = document.getElementById('dbTableScroll');
    const dbDataHead     = document.getElementById('dbDataHead');
    const dbDataBody     = document.getElementById('dbDataBody');
    const dbPagination   = document.getElementById('dbPagination');
    const dbPrevPage     = document.getElementById('dbPrevPage');
    const dbNextPage     = document.getElementById('dbNextPage');
    const dbPageInfo     = document.getElementById('dbPageInfo');

    // ── Load table list ──────────────────────────────────────────────────────
    async function loadTables() {
        const res  = await fetch(`${api}?action=tables`);
        const data = await res.json();

        tableList.innerHTML = '';
        if (!data.length) {
            tableList.innerHTML = '<li class="db-table-item muted">No tables found.</li>';
            return;
        }

        data.forEach(({ name, rows }) => {
            const li = document.createElement('li');
            li.className = 'db-table-item';
            li.innerHTML = `<span class="db-tname">${esc(name)}</span><span class="db-tcount">${rows}</span>`;
            li.addEventListener('click', () => selectTable(name));
            tableList.appendChild(li);
        });
    }

    // ── Select a table ───────────────────────────────────────────────────────
    async function selectTable(name) {
        currentTable  = name;
        currentOffset = 0;

        // Highlight active row
        tableList.querySelectorAll('.db-table-item').forEach(el => {
            el.classList.toggle('active', el.querySelector('.db-tname')?.textContent === name);
        });

        dbTableTitle.textContent = name;
        dbSchemaToggle.disabled  = false;

        // Hide schema panel when switching tables
        dbSchemaPanel.classList.add('hidden');
        dbSchemaToggle.textContent = 'Schema';

        await Promise.all([loadSchema(name), loadRows()]);
    }

    // ── Load schema ──────────────────────────────────────────────────────────
    async function loadSchema(name) {
        const res  = await fetch(`${api}?action=schema&table=${encodeURIComponent(name)}`);
        const cols = await res.json();

        dbSchemaBody.innerHTML = cols.map(c => `
            <tr>
                <td>${esc(c.name)}</td>
                <td><code>${esc(c.type || '—')}</code></td>
                <td>${c.notnull ? 'YES' : '—'}</td>
                <td>${c.dflt_value !== null ? esc(c.dflt_value) : '—'}</td>
                <td>${c.pk ? '✓' : '—'}</td>
            </tr>`).join('');
    }

    // ── Load rows ────────────────────────────────────────────────────────────
    async function loadRows() {
        const res  = await fetch(`${api}?action=rows&table=${encodeURIComponent(currentTable)}&limit=${PAGE}&offset=${currentOffset}`);
        const data = await res.json();

        totalRows = data.total;
        dbRowCount.textContent = `${totalRows.toLocaleString()} row${totalRows !== 1 ? 's' : ''}`;

        if (!data.rows.length && currentOffset === 0) {
            dbEmptyState.textContent = 'This table is empty.';
            dbEmptyState.classList.remove('hidden');
            dbTableScroll.classList.add('hidden');
            dbPagination.classList.add('hidden');
            return;
        }

        dbEmptyState.classList.add('hidden');
        dbTableScroll.classList.remove('hidden');

        const cols = Object.keys(data.rows[0] || {});

        // Header
        dbDataHead.innerHTML = `<tr>${cols.map(c => `<th>${esc(c)}</th>`).join('')}</tr>`;

        // Rows
        dbDataBody.innerHTML = data.rows.map(row =>
            `<tr>${cols.map(c => `<td>${row[c] === null ? '<span class="db-null">NULL</span>' : esc(String(row[c]))}</td>`).join('')}</tr>`
        ).join('');

        // Pagination
        const start = currentOffset + 1;
        const end   = Math.min(currentOffset + PAGE, totalRows);
        dbPageInfo.textContent = `${start}–${end} of ${totalRows.toLocaleString()}`;
        dbPrevPage.disabled = currentOffset === 0;
        dbNextPage.disabled = end >= totalRows;
        dbPagination.classList.remove('hidden');
    }

    // ── Schema toggle ────────────────────────────────────────────────────────
    dbSchemaToggle.addEventListener('click', () => {
        const hidden = dbSchemaPanel.classList.toggle('hidden');
        dbSchemaToggle.textContent = hidden ? 'Schema' : 'Hide Schema';
    });

    // ── Pagination ───────────────────────────────────────────────────────────
    dbPrevPage.addEventListener('click', () => {
        if (currentOffset > 0) { currentOffset -= PAGE; loadRows(); }
    });
    dbNextPage.addEventListener('click', () => {
        if (currentOffset + PAGE < totalRows) { currentOffset += PAGE; loadRows(); }
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    loadTables();
})();
