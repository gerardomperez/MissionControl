/* ============================================================
   journal.js — Journal Page
   ============================================================ */

const JOURNAL_API = (window.BASE_URL || '') + '/api/journal.php';

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    return new Intl.DateTimeFormat('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
    }).format(d);
}

function renderList(entries) {
    const list = document.getElementById('journalList');
    if (!list) return;

    if (!entries || entries.length === 0) {
        list.innerHTML = '<div class="report-list-loading">No entries yet.</div>';
        return;
    }

    list.innerHTML = entries.map(e => `
        <div class="report-row journal-row">
            <div class="report-row-left">
                <div class="journal-entry-text">${escHtml(e.content)}</div>
                <div class="report-meta">
                    <span>${escHtml(formatDate(e.created_at))}</span>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadEntries() {
    const list = document.getElementById('journalList');
    try {
        const res     = await fetch(JOURNAL_API);
        const entries = await res.json();
        renderList(Array.isArray(entries) ? entries : []);
    } catch (e) {
        if (list) list.innerHTML = '<div class="report-list-loading">Failed to load entries.</div>';
        console.error(e);
    }
}

document.getElementById('journalForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const textarea = document.getElementById('journalEntry');
    const content   = textarea.value.trim();
    if (!content) return;

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
        const res = await fetch(JOURNAL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content }),
        });
        const saved = await res.json();
        if (saved.error) throw new Error(saved.error);
        textarea.value = '';
        await loadEntries();
    } catch (err) {
        alert('Failed to save entry: ' + err.message);
    } finally {
        submitBtn.disabled = false;
    }
});

loadEntries();
