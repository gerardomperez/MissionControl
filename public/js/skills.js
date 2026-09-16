/* ============================================================
   skills.js — Skills Registry Page
   ============================================================ */

const SKILLS_API = (window.BASE_URL || '') + '/api/skills.php';

/* ---- Utilities ---- */

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ---- State ---- */
let allSkills = [];

/* ---- Render list ---- */

function renderList(skills) {
    const list = document.getElementById('skillsList');
    const countEl = document.getElementById('skillsCount');
    if (!list) return;

    if (!skills || skills.length === 0) {
        list.innerHTML = '<div class="skills-list-loading">No skills found.</div>';
        if (countEl) countEl.textContent = '';
        return;
    }

    if (countEl) countEl.textContent = `${skills.length} skill${skills.length !== 1 ? 's' : ''}`;

    list.innerHTML = skills.map(s => {
        const descLen = (s.description || '').length;
        const longDesc = descLen > 800;
        return `
        <button class="skill-row${longDesc ? ' skill-row--long-desc' : ''}" data-id="${escHtml(s.id)}">
            <div class="skill-row-left">
                <div class="skill-name">${escHtml(s.name)}</div>
                <div class="skill-description">${escHtml(s.description || 'No description')}</div>
                <div class="skill-char-count">${descLen} chars</div>
            </div>
            <div class="skill-row-right">
                ${longDesc ? '<span class="skill-desc-warn" title="Description exceeds 200 characters">&#9888;</span>' : ''}
                ${s.status ? `<span class="skill-status skill-status--${escHtml(s.status)}">${escHtml(s.status)}</span>` : ''}
                <span class="skill-row-chevron">&#8250;</span>
            </div>
        </button>`;
    }).join('');
}

/* ---- Filter ---- */

function applyFilter() {
    const filter = document.getElementById('skillsFilter')?.value || 'all';
    const filtered = filter === 'all'
        ? allSkills
        : allSkills.filter(s => (s.status || 'unknown') === filter);
    renderList(filtered);
}

document.getElementById('skillsFilter')?.addEventListener('change', applyFilter);

/* ---- Modal ---- */

const modal      = document.getElementById('skillModal');
const modalTitle = document.getElementById('skillModalTitle');
const modalContent = document.getElementById('skillModalContent');
const closeBtn   = document.getElementById('skillModalClose');

function openModal(skill) {
    modalTitle.textContent = skill.name;
    
    // Render markdown content as HTML
    const contentHtml = skill.content ? renderMarkdown(skill.content) : '<p>No content available.</p>';
    modalContent.innerHTML = contentHtml;
    modalContent.scrollTop = 0;

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

/* ---- Simple Markdown renderer ---- */

function renderMarkdown(raw) {
    if (!raw) return '';

    let html = escHtml(raw);

    // Headers
    html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

    // Horizontal rule
    html = html.replace(/^---+$/gm, '<hr>');

    // Bold and italic
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
    html = html.replace(/_(.+?)_/g, '<em>$1</em>');

    // Code blocks
    html = html.replace(/```([\w]*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>');

    // Inline code
    html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');

    // Bullet lists
    html = html.replace(/^[*-] (.+)$/gm, '<li data-list="ul">$1</li>');
    html = html.replace(/(<li data-list="ul">.*?<\/li>\n?)+/gs, m =>
        '<ul>' + m.replace(/ data-list="ul"/g, '') + '</ul>'
    );

    // Numbered lists
    html = html.replace(/^\d+\. (.+)$/gm, '<li data-list="ol">$1</li>');
    html = html.replace(/(<li data-list="ol">.*?<\/li>\n?)+/gs, m =>
        '<ol>' + m.replace(/ data-list="ol"/g, '') + '</ol>'
    );

    // Paragraphs (lines that aren't tags)
    html = html.replace(/^(?!<[a-z])(.+)$/gm, '<p>$1</p>');

    return html;
}

/* ---- Row click — fetch full skill ---- */

document.getElementById('skillsList')?.addEventListener('click', async (e) => {
    const row = e.target.closest('.skill-row');
    if (!row) return;

    const id = row.dataset.id;

    // Show modal immediately with loading state
    modalTitle.textContent       = 'Loading…';
    modalContent.innerHTML          = '<div style="padding:20px 0;color:var(--text-muted);font-family:var(--font-mono);font-size:13px">Fetching skill content…</div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const res    = await fetch(`${SKILLS_API}?id=${encodeURIComponent(id)}`);
        const skill = await res.json();
        if (skill.error) throw new Error(skill.error);
        openModal(skill);
    } catch (err) {
        modalTitle.textContent = 'Error';
        modalContent.innerHTML    = `<div style="color:var(--danger);font-family:var(--font-mono);font-size:13px">Failed to load: ${escHtml(err.message)}</div>`;
    }
});

/* ---- Init ---- */

async function loadSkills() {
    try {
        const res    = await fetch(SKILLS_API);
        const skills = await res.json();
        allSkills    = Array.isArray(skills) ? skills : [];
        applyFilter();
    } catch (e) {
        const list = document.getElementById('skillsList');
        if (list) list.innerHTML = '<div class="skills-list-loading">Failed to load skills.</div>';
        console.error(e);
    }
}

loadSkills();