/* ============================================================
   calendar.js — Scheduled Tasks Calendar
   ============================================================ */

const EVENTS_API = (window.BASE_URL || '') + '/api/events.php';

/* ---- Date helpers ---- */

/** Return a Date set to midnight local time for the given Date */
function midnight(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

/** Monday of the week containing date d */
function mondayOf(d) {
    const day = d.getDay();                     // 0=Sun … 6=Sat
    const diff = (day === 0) ? -6 : 1 - day;   // shift to Monday
    return midnight(new Date(d.getFullYear(), d.getMonth(), d.getDate() + diff));
}

/** Add N days to a Date, returning a new Date */
function addDays(d, n) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
}

/** ISO date string YYYY-MM-DD */
function toISO(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/** "Jan 5" or "Mar 28" */
function fmtMonthDay(d) {
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(d);
}

/** "9:00 AM" from ISO datetime string */
function fmtTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return '';
    return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(d);
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ---- State ---- */

const today     = midnight(new Date());
// Default anchor: Monday of the week one week ago → gives current week in row 2
let anchorMonday = addDays(mondayOf(today), -7);

/* ---- Window calculation ---- */

function windowStart() { return anchorMonday; }
function windowEnd()   { return addDays(anchorMonday, 34); }  // 5 weeks = 35 days

/* ---- Effective display status ---- */

function effectiveStatus(event) {
    const scheduled = new Date(event.scheduled_at);
    const isPast    = scheduled < today;

    if (!isPast) {
        // Future events always show as pending regardless of DB value
        return 'pending';
    }
    // Past events: if still pending in DB → overdue
    if (event.status === 'pending') return 'overdue';
    return event.status;  // success | failed
}

function statusLabel(status) {
    return { success: 'Success', failed: 'Failed', pending: 'Pending', overdue: 'Overdue' }[status] || status;
}

/* ---- Model display helpers ---- */

/** Shorten model name for display: "anthropic/claude-sonnet-4-6" → "claude-sonnet" */
function shortModel(model) {
    if (!model) return '';
    // Strip provider prefix
    const name = model.includes('/') ? model.split('/').pop() : model;
    // Further shorten common names
    const aliases = {
        'claude-sonnet-4-6':    'claude-sonnet',
        'claude-opus-4-6':      'claude-opus',
        'claude-haiku-4-5':     'claude-haiku',
        'gpt-4o':               'gpt-4o',
        'gpt-4o-mini':          'gpt-4o-mini',
        'o1':                   'o1',
        'gemini-2.5-flash-lite':'gemini-flash',
        'gemini-2.0-flash-exp': 'gemini-flash',
        'gemini-1.5-pro':       'gemini-pro',
        'kimi-k2.5':            'kimi-k2',
    };
    return aliases[name] || name.replace(/-\d{4}-\d{2}-\d{2}$/, '').substring(0, 14);
}

/** Pick a subtle color class for the model badge */
function modelColorClass(model) {
    if (!model) return 'cal-model--default';
    const m = model.toLowerCase();
    if (m.includes('claude'))  return 'cal-model--claude';
    if (m.includes('gpt') || m.includes('o1') || m.includes('openai')) return 'cal-model--openai';
    if (m.includes('gemini'))  return 'cal-model--gemini';
    if (m.includes('kimi') || m.includes('moonshot')) return 'cal-model--kimi';
    return 'cal-model--default';
}

/* ---- Build event chip ---- */

function buildEventChip(event) {
    const status  = effectiveStatus(event);
    const time    = fmtTime(event.scheduled_at);
    const model   = event.model || '';
    const source  = event.source         ? `Source: ${event.source}` : '';
    const cron    = event.cron_expression ? `Cron: ${event.cron_expression}` : '';
    const desc    = event.description    || '';

    const modelHtml = model
        ? `<div class="cal-event-model ${modelColorClass(model)}">${escHtml(shortModel(model))}</div>`
        : '';

    const tooltipModel = model ? `<div class="cal-tooltip-row">Model: ${escHtml(model)}</div>` : '';
    const tooltipRows = [source, cron, desc].filter(Boolean)
        .map(t => `<div class="cal-tooltip-row">${escHtml(t)}</div>`)
        .join('');

    return `
        <div class="cal-event cal-event--${status}" title="">
            <div class="cal-event-time">${escHtml(time)}</div>
            <div class="cal-event-title">${escHtml(event.title)}</div>
            <div class="cal-event-footer">
                <div class="cal-event-pill">${escHtml(statusLabel(status))}</div>
                ${modelHtml}
            </div>
            <div class="cal-event-tooltip">
                <div class="cal-tooltip-title">${escHtml(event.title)}</div>
                <div class="cal-tooltip-row">${escHtml(time)}</div>
                ${tooltipModel}
                ${tooltipRows}
            </div>
        </div>`;
}

/* ---- Render grid ---- */

function renderGrid(events) {
    const grid = document.getElementById('calGrid');
    if (!grid) return;

    // Build a map: "YYYY-MM-DD" → [events]
    const byDate = {};
    for (const ev of events) {
        const key = toISO(new Date(ev.scheduled_at));
        if (!byDate[key]) byDate[key] = [];
        byDate[key].push(ev);
    }

    const start = windowStart();
    const end   = windowEnd();
    const cells = [];

    for (let i = 0; i < 35; i++) {
        const cellDate  = addDays(start, i);
        const dateKey   = toISO(cellDate);
        const isPast    = cellDate < today;
        const isToday   = dateKey === toISO(today);
        const dayEvents = byDate[dateKey] || [];

        const cellClass = [
            'cal-cell',
            isPast && !isToday ? 'cal-cell--past' : '',
            isToday            ? 'cal-cell--today' : '',
        ].filter(Boolean).join(' ');

        const numClass = isToday ? 'cal-day-num cal-day-num--today' : 'cal-day-num';

        // Render up to 3 events; overflow link for the rest
        const MAX_VISIBLE = 3;
        const visibleEvs  = dayEvents.slice(0, MAX_VISIBLE);
        const overflow    = dayEvents.length - MAX_VISIBLE;

        const chipsHtml   = visibleEvs.map(buildEventChip).join('');
        const overflowHtml = overflow > 0
            ? `<span class="cal-overflow">+${overflow} more</span>`
            : '';

        cells.push(`
            <div class="${cellClass}" data-date="${dateKey}">
                <div class="${numClass}">${cellDate.getDate()}</div>
                <div class="cal-events">${chipsHtml}</div>
                ${overflowHtml}
            </div>`);
    }

    grid.innerHTML = cells.join('');
    updateHeader();
}

/* ---- Header text ---- */

function updateHeader() {
    const rangeEl = document.getElementById('calDateRange');
    if (!rangeEl) return;
    const start = windowStart();
    const end   = windowEnd();
    rangeEl.textContent = `${fmtMonthDay(start)} – ${fmtMonthDay(end)}`;
}

/* ---- Fetch & render ---- */

async function loadAndRender() {
    const start = toISO(windowStart());
    const end   = toISO(windowEnd());

    const grid = document.getElementById('calGrid');
    if (grid) grid.innerHTML = '<div style="padding:24px;color:var(--text-muted);font-family:var(--font-mono);font-size:13px;grid-column:1/-1">Loading…</div>';
    updateHeader();

    try {
        const res    = await fetch(`${EVENTS_API}?start=${start}&end=${end}`);
        const events = await res.json();
        renderGrid(Array.isArray(events) ? events : []);
    } catch (e) {
        if (grid) grid.innerHTML = '<div style="padding:24px;color:var(--danger);font-family:var(--font-mono);font-size:13px;grid-column:1/-1">Failed to load events.</div>';
        console.error(e);
    }
}

/* ---- Navigation ---- */

document.getElementById('calPrev')?.addEventListener('click', () => {
    anchorMonday = addDays(anchorMonday, -7);
    loadAndRender();
});

document.getElementById('calNext')?.addEventListener('click', () => {
    anchorMonday = addDays(anchorMonday, 7);
    loadAndRender();
});

document.getElementById('calToday')?.addEventListener('click', () => {
    anchorMonday = addDays(mondayOf(today), -7);
    loadAndRender();
});

/* ---- Init ---- */
loadAndRender();
