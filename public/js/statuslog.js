/* ============================================================
   statuslog.js — Status Reports Page
   ============================================================ */

const REPORTS_API = (window.BASE_URL || '') + '/api/reports.php';

/* ---- Utilities ---- */

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatDate(iso) {
    if (!iso) return '';
    const utcIso = (iso.includes('T') || iso.endsWith('Z'))
        ? iso
        : iso.replace(' ', 'T') + 'Z';
    const d = new Date(utcIso);
    if (isNaN(d)) return iso;
    return new Intl.DateTimeFormat('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
        timeZone: 'America/New_York',
    }).format(d);
}

/* ---- Render report content ---- */

function renderReportContent(content) {
    if (!content) return '';

    // Full HTML document — extract <body> content, strip embedded <style> blocks
    if (content.trim().match(/^<!DOCTYPE/i) || content.trim().match(/^<html/i)) {
        const bodyMatch = content.match(/<body[^>]*>([\s\S]*)<\/body>/i);
        let inner = bodyMatch ? bodyMatch[1].trim() : content;
        // Remove any <style>…</style> blocks injected by the report generator
        inner = inner.replace(/<style[\s\S]*?<\/style>/gi, '');
        return inner;
    }

    // Partial HTML snippet (starts with a tag) — strip style blocks and render
    if (content.trim().startsWith('<')) {
        return content.replace(/<style[\s\S]*?<\/style>/gi, '');
    }

    // Plain markdown fallback for old reports
    return renderMarkdown(content);
}

/* ---- Lightweight Markdown renderer (fallback) ---- */

function renderMarkdown(raw) {
    if (!raw) return '';

    let html = escHtml(raw);

    // Headings
    html = html.replace(/^### (.+)$/gm,  '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm,   '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm,    '<h1>$1</h1>');

    // Horizontal rule
    html = html.replace(/^---+$/gm, '<hr>');

    // Bold
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__(.+?)__/g,     '<strong>$1</strong>');

    // Inline code
    html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');

    // Fenced code blocks
    html = html.replace(/```[\w]*\n([\s\S]*?)```/g, (_, code) => {
        return '<pre><code>' + code.trimEnd() + '</code></pre>';
    });

    // Bullet list items
    html = html.replace(/^[*-] (.+)$/gm, '<li data-list="ul">$1</li>');

    // Numbered list items
    html = html.replace(/^\d+\. (.+)$/gm, '<li data-list="ol">$1</li>');

    // Wrap <li> runs into <ul> or <ol>
    html = html.replace(/(<li data-list="ul">.*?<\/li>\n?)+/gs, m =>
        '<ul>' + m.replace(/ data-list="ul"/g, '') + '</ul>'
    );
    html = html.replace(/(<li data-list="ol">.*?<\/li>\n?)+/gs, m =>
        '<ol>' + m.replace(/ data-list="ol"/g, '') + '</ol>'
    );

    return html;
}

/* ---- Render list ---- */

function renderList(reports) {
    const list = document.getElementById('reportList');
    if (!list) return;

    if (!reports || reports.length === 0) {
        list.innerHTML = '<div class="report-list-loading">No reports found.</div>';
        return;
    }

    list.innerHTML = reports.map(r => {
        const agentHtml = r.source_agent
            ? `<span class="report-agent-dot"></span><span>${escHtml(r.source_agent)}</span><span>&middot;</span>`
            : '';
        return `
            <button class="report-row" data-id="${r.id}">
                <div class="report-row-left">
                    <div class="report-title">${escHtml(r.title)}</div>
                    <div class="report-meta">
                        ${agentHtml}
                        <span>${escHtml(formatDate(r.created_at))}</span>
                    </div>
                </div>
                <span class="report-row-chevron">&#8250;</span>
            </button>
        `;
    }).join('');
}

/* ---- Modal ---- */

const modal      = document.getElementById('reportModal');
const modalTitle = document.getElementById('reportModalTitle');
const modalMeta  = document.getElementById('reportModalMeta');
const modalBody  = document.getElementById('reportModalBody');
const closeBtn   = document.getElementById('reportModalClose');

function openModal(report) {
    modalTitle.textContent = report.title;

    const agentHtml = report.source_agent
        ? `<span class="report-agent-dot"></span><span>${escHtml(report.source_agent)}</span><span>&middot;</span>`
        : '';
    modalMeta.innerHTML = agentHtml + `<span>${escHtml(formatDate(report.created_at))}</span>`;

    const rendered = renderReportContent(report.content || report.body || '');
    modalBody.innerHTML = `<div class="report-body-content">${rendered}</div>`;
    modalBody.scrollTop = 0;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

closeBtn?.addEventListener('click', closeModal);

modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
});

/* ---- Row click — fetch full report ---- */

document.getElementById('reportList')?.addEventListener('click', async (e) => {
    const row = e.target.closest('.report-row');
    if (!row) return;

    const id = row.dataset.id;

    // Show modal immediately with loading state
    modalTitle.textContent       = 'Loading\u2026';
    modalMeta.innerHTML          = '';
    modalBody.innerHTML          = '<div style="padding:20px 0;color:var(--text-muted);font-family:var(--font-mono);font-size:13px">Fetching report\u2026</div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const res    = await fetch(`${REPORTS_API}?id=${encodeURIComponent(id)}`);
        const report = await res.json();
        if (report.error) throw new Error(report.error);
        openModal(report);
    } catch (err) {
        modalTitle.textContent = 'Error';
        modalBody.innerHTML    = `<div style="color:var(--danger);font-family:var(--font-mono);font-size:13px">Failed to load: ${escHtml(err.message)}</div>`;
    }
});

/* ---- Init ---- */

async function loadReports() {
    try {
        const res     = await fetch(REPORTS_API);
        const reports = await res.json();
        renderList(Array.isArray(reports) ? reports : []);
    } catch (e) {
        const list = document.getElementById('reportList');
        if (list) list.innerHTML = '<div class="report-list-loading">Failed to load reports.</div>';
        console.error(e);
    }
}

loadReports();
