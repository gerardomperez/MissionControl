/* ============================================================
   team.js — Team / Subagent Roster Page
   ============================================================ */

const TEAM_API = (window.BASE_URL || '') + '/api/agents.php';

let allAgents = [];
let activeFilter = 'all';

/* ---- Utilities ---- */

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function statusBadge(status) {
    const labels = { active: 'Active', standby: 'Standby', idle: 'Idle', offline: 'Offline' };
    return `<span class="status-badge status-badge--${escHtml(status || 'idle')}">
                <span class="dot"></span>${escHtml(labels[status] || status)}
            </span>`;
}

/* ---- Card builders ---- */

function specTagsHtml(specializations) {
    if (!specializations) return '';
    const tags = specializations.split(',').map(s => s.trim()).filter(Boolean);
    if (!tags.length) return '';
    return `<div class="agent-specializations">
        ${tags.map(s => `<span class="spec-tag">${escHtml(s)}</span>`).join('')}
    </div>`;
}

function formatModel(model) {
    if (!model) return { short: '', tier: '', tierClass: '' };
    
    // Extract tier from model name
    let tier = '';
    let tierClass = '';
    if (model.includes('opus')) {
        tier = 'T1';
        tierClass = 'tier-1';
    } else if (model.includes('sonnet')) {
        tier = 'T2';
        tierClass = 'tier-2';
    } else if (model.includes('haiku')) {
        tier = 'T3';
        tierClass = 'tier-3';
    }
    
    // Shorten model name for display
    let short = model.replace('anthropic/', '').replace('claude-', '');
    
    return { short, tier, tierClass };
}

function buildCard(agent, featured = false) {
    const isHenry   = agent.name === 'Henry';
    const cardClass = ['agent-card',
        isHenry   ? 'agent-card--henry'    : '',
        featured  ? 'agent-card--featured' : '',
    ].filter(Boolean).join(' ');

    const modelInfo = formatModel(agent.model);
    const modelDisplay = agent.model 
        ? `<span class="agent-model ${escHtml(modelInfo.tierClass)}" title="${escHtml(agent.model)}">
             <span class="model-tier">${escHtml(modelInfo.tier)}</span>
             ${escHtml(modelInfo.short)}
           </span>` 
        : '';

    return `
        <div class="${cardClass}" data-status="${escHtml(agent.status)}">
            <div class="agent-card-header">
                <div class="agent-card-header-left">
                    <span class="agent-emoji">${escHtml(agent.avatar_emoji || '🤖')}</span>
                    <div>
                        <div class="agent-name">${escHtml(agent.name)}</div>
                        <div class="agent-role">${escHtml(agent.role)}</div>
                    </div>
                </div>
                ${statusBadge(agent.status)}
            </div>
            <div class="agent-card-body">
                ${modelDisplay}
                ${agent.description
                    ? `<p class="agent-description">${escHtml(agent.description)}</p>`
                    : ''}
                ${specTagsHtml(agent.specializations)}
            </div>
        </div>
    `;
}

/* ---- Render ---- */

function renderGrid() {
    const henryRow     = document.getElementById('henryRow');
    const grid         = document.getElementById('teamGrid');
    const sectionLabel = document.getElementById('subagentLabel');
    if (!grid) return;

    const henry     = allAgents.find(a => a.name === 'Henry');
    const subagents = allAgents.filter(a => a.name !== 'Henry');

    const henryVisible = henry && (activeFilter === 'all' || henry.status === activeFilter);
    const filtered     = activeFilter === 'all'
        ? subagents
        : subagents.filter(a => a.status === activeFilter);

    // Henry row
    if (henryRow) {
        if (henryVisible && henry) {
            henryRow.innerHTML = buildCard(henry, true);
            henryRow.classList.remove('hidden');
        } else {
            henryRow.classList.add('hidden');
        }
    }

    // Section label
    if (sectionLabel) {
        sectionLabel.classList.toggle('hidden', filtered.length === 0);
    }

    // Subagent grid
    if (filtered.length === 0) {
        grid.innerHTML = '<div class="team-loading">No agents match this filter.</div>';
    } else {
        grid.innerHTML = filtered.map(a => buildCard(a, false)).join('');
    }
}

function updateSubtitle() {
    const el = document.getElementById('teamSubtitle');
    if (!el) return;
    const total = allAgents.length;
    el.textContent = `Henry's subagent fleet — ${total} agent${total !== 1 ? 's' : ''}`;
}

/* ---- Load ---- */

async function loadAgents() {
    try {
        const res = await fetch(TEAM_API);
        allAgents = await res.json();
    } catch (e) {
        console.error('Failed to load agents:', e);
        allAgents = [];
    }
    updateSubtitle();
    renderGrid();
}

/* ---- Filter Pills ---- */

document.getElementById('filterPills')?.addEventListener('click', (e) => {
    const pill = e.target.closest('.filter-pill');
    if (!pill) return;

    activeFilter = pill.dataset.filter;

    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('filter-pill--active'));
    pill.classList.add('filter-pill--active');

    renderGrid();
});

/* ---- Init ---- */
loadAgents();
