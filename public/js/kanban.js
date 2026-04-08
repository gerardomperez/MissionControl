/* ============================================================
   Kanban.js — Orchestrator Page
   ============================================================ */

const API_BASE = (window.BASE_URL || '') + '/api/tasks.php';

const STATUSES = [
    { key: 'backlog',     label: 'Backlog' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'blocked',     label: 'Blocked' },
    { key: 'review',      label: 'Ready for Review' },
    { key: 'done',        label: 'Done' },
];

// Whether to show hidden Done tasks
let showHidden = false;

// All tasks from last fetch
let allTasks = [];

/* ---- Utilities ---- */

function formatTimestamp(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return '';
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day:   'numeric',
        hour:  'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(d);
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ---- Fetch & Render ---- */

async function loadTasks() {
    const url = showHidden ? API_BASE + '?include_hidden=1' : API_BASE;
    try {
        const res = await fetch(url);
        allTasks = await res.json();
    } catch (e) {
        console.error('Failed to load tasks:', e);
        allTasks = [];
    }
    renderBoard();
}

function renderBoard() {
    STATUSES.forEach(({ key }) => {
        const container = document.getElementById('col-' + key);
        const countEl   = document.getElementById('count-' + key);
        if (!container) return;

        const tasks = allTasks.filter(t => t.status === key);

        // Update count — for Done, count visible (non-hidden when showHidden=false)
        const visibleCount = tasks.filter(t => key !== 'done' || showHidden || !t.hidden).length;
        countEl.textContent = visibleCount;

        container.innerHTML = '';

        const visible = tasks.filter(t => key !== 'done' || showHidden || !t.hidden);

        if (visible.length === 0) {
            container.innerHTML = '<div class="kanban-empty">No tasks</div>';
            return;
        }

        visible.forEach(task => {
            container.appendChild(buildCard(task));
        });
    });

    updateDoneToggle();
}

function buildCard(task) {
    const card = document.createElement('div');
    card.className = 'task-card' + (task.hidden ? ' hidden-task' : '');
    card.dataset.id = task.id;

    const assigneeHtml = task.assignee
        ? `<span class="assignee-pill">${escHtml(task.assignee)}</span>`
        : `<span class="assignee-pill unassigned">Unassigned</span>`;

    const timeHtml = task.status_changed_at
        ? `<span class="task-time">${escHtml(formatTimestamp(task.status_changed_at))}</span>`
        : '';

    const hideBtnHtml = task.status === 'done'
        ? `<button class="hide-btn" data-id="${task.id}" data-hidden="${task.hidden ? 0 : 1}">
               ${task.hidden ? 'Unhide' : '&#10005; Hide'}
           </button>`
        : '';

    card.innerHTML = `
        <div class="task-actions">
            <div class="dropdown-wrapper">
                <button class="task-menu-btn" title="Move to…" data-id="${task.id}" aria-label="Task options">&#8942;</button>
                <div class="dropdown-menu hidden" id="menu-${task.id}">
                    ${STATUSES.map(s => `
                        <button class="dropdown-item${task.status === s.key ? ' active' : ''}"
                                data-move-id="${task.id}" data-move-status="${s.key}">
                            ${escHtml(s.label)}
                        </button>`).join('')}
                    <hr style="border:none;border-top:1px solid var(--border);margin:4px 0">
                    <button class="dropdown-item" style="color:var(--danger)"
                            data-delete-id="${task.id}">
                        Delete task
                    </button>
                </div>
            </div>
        </div>
        <div class="task-card-title task-card-title--clickable" data-open-id="${task.id}">${escHtml(task.title)}</div>
        ${task.description ? `<div class="task-card-desc">${escHtml(task.description)}</div>` : ''}
        <div class="task-card-footer">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                ${assigneeHtml}
                ${hideBtnHtml}
            </div>
            ${timeHtml}
        </div>
    `;

    return card;
}

function updateDoneToggle() {
    const btn = document.getElementById('doneToggleBtn');
    if (!btn) return;

    const hiddenCount = allTasks.filter(t => t.status === 'done' && t.hidden).length;

    if (showHidden) {
        btn.textContent = '👁 Hide completed';
    } else if (hiddenCount > 0) {
        btn.textContent = `Show hidden (${hiddenCount})`;
    } else {
        btn.textContent = '👁 Hide';
    }
}

/* ---- API Calls ---- */

async function patchTask(body) {
    const res = await fetch(API_BASE, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return res.json();
}

async function deleteTask(id) {
    await fetch(API_BASE, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
}

async function createTask(data) {
    const res = await fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    return res.json();
}

/* ---- Event Delegation ---- */

document.addEventListener('click', async (e) => {

    // Close all open dropdowns when clicking outside
    if (!e.target.closest('.dropdown-wrapper')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
    }

    // Open / toggle task menu
    const menuBtn = e.target.closest('.task-menu-btn');
    if (menuBtn) {
        e.stopPropagation();
        const id = menuBtn.dataset.id;
        const menu = document.getElementById('menu-' + id);
        if (!menu) return;
        const wasHidden = menu.classList.contains('hidden');
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        if (wasHidden) menu.classList.remove('hidden');
        return;
    }

    // Move task to status
    const moveBtn = e.target.closest('[data-move-id]');
    if (moveBtn) {
        const id     = parseInt(moveBtn.dataset.moveId);
        const status = moveBtn.dataset.moveStatus;
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        await patchTask({ id, status });
        await loadTasks();
        return;
    }

    // Delete task
    const delBtn = e.target.closest('[data-delete-id]');
    if (delBtn) {
        const id = parseInt(delBtn.dataset.deleteId);
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        if (confirm('Delete this task?')) {
            await deleteTask(id);
            await loadTasks();
        }
        return;
    }

    // Hide / Unhide done task
    const hideBtn = e.target.closest('.hide-btn');
    if (hideBtn) {
        const id     = parseInt(hideBtn.dataset.id);
        const hidden = parseInt(hideBtn.dataset.hidden);
        await patchTask({ id, hidden });
        await loadTasks();
        return;
    }

    // Done column toggle
    const doneToggle = e.target.closest('#doneToggleBtn');
    if (doneToggle) {
        showHidden = !showHidden;
        await loadTasks();
        return;
    }

    // Open task detail on title click
    const titleEl = e.target.closest('.task-card-title--clickable');
    if (titleEl) {
        const id   = parseInt(titleEl.dataset.openId);
        const task = allTasks.find(t => t.id === id);
        if (task) openDetailModal(task);
        return;
    }
});

/* ---- New Task Modal ---- */

const modal       = document.getElementById('newTaskModal');
const newTaskBtn  = document.getElementById('newTaskBtn');
const closeBtn    = document.getElementById('newTaskModalClose');
const cancelBtn   = document.getElementById('newTaskCancel');
const form        = document.getElementById('newTaskForm');

function openModal() {
    form.reset();
    modal.classList.remove('hidden');
    document.getElementById('taskTitle').focus();
}

function closeModal() {
    modal.classList.add('hidden');
}

newTaskBtn?.addEventListener('click', openModal);
closeBtn?.addEventListener('click', closeModal);
cancelBtn?.addEventListener('click', closeModal);

modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeModal();
    }
});

form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
        title:       document.getElementById('taskTitle').value.trim(),
        description: document.getElementById('taskDesc').value.trim(),
        assignee:    document.getElementById('taskAssignee').value.trim(),
        status:      document.getElementById('taskStatus').value,
    };
    if (!data.title) return;

    const submitBtn = form.querySelector('[type=submit]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating…';

    try {
        await createTask(data);
        closeModal();
        await loadTasks();
    } catch (err) {
        console.error('Failed to create task:', err);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Task';
    }
});

/* ---- Task Detail Modal ---- */

const COMMENTS_API = (window.BASE_URL || '') + '/api/comments.php';

const detailModal    = document.getElementById('taskDetailModal');
const detailTitle    = document.getElementById('taskDetailTitle');
const detailMeta     = document.getElementById('taskDetailMeta');
const detailDesc     = document.getElementById('taskDetailDesc');
const detailNotes    = document.getElementById('taskDetailNotes');
const detailClose    = document.getElementById('taskDetailClose');
const detailCancel   = document.getElementById('taskDetailCancel');
const detailSaveBtn  = document.getElementById('taskDetailSave');
const commentLog     = document.getElementById('commentLog');

const STATUS_LABELS = {
    backlog:     'Backlog',
    in_progress: 'In Progress',
    blocked:     'Blocked',
    review:      'Ready for Review',
    done:        'Done',
};

let detailTaskId = null;

function formatCommentTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    return new Intl.DateTimeFormat('en-US', {
        month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
    }).format(d);
}

function renderCommentLog(comments) {
    if (!commentLog) return;
    if (!comments || comments.length === 0) {
        commentLog.innerHTML = '<div class="comment-log-empty">No comments yet.</div>';
        return;
    }
    commentLog.innerHTML = comments.map(c => {
        const isUser     = c.author === 'G';
        const avatarCls  = isUser ? 'comment-avatar--user' : 'comment-avatar--agent';
        const initial    = escHtml(c.author.charAt(0).toUpperCase());
        return `
            <div class="comment-entry">
                <div class="comment-avatar ${avatarCls}">${initial}</div>
                <div class="comment-bubble">
                    <div class="comment-header">
                        <span class="comment-author">${escHtml(c.author)}</span>
                        <span class="comment-time">${escHtml(formatCommentTime(c.created_at))}</span>
                    </div>
                    <div class="comment-body">${escHtml(c.body)}</div>
                </div>
            </div>`;
    }).join('');
    // Scroll to bottom so newest comment is visible
    commentLog.scrollTop = commentLog.scrollHeight;
}

async function loadComments(taskId) {
    if (commentLog) commentLog.innerHTML = '<div class="comment-log-empty">Loading…</div>';
    try {
        const res      = await fetch(`${COMMENTS_API}?task_id=${taskId}`);
        const comments = await res.json();
        renderCommentLog(Array.isArray(comments) ? comments : []);
    } catch (e) {
        if (commentLog) commentLog.innerHTML = '<div class="comment-log-empty">Failed to load comments.</div>';
    }
}

async function openDetailModal(task) {
    detailTaskId = task.id;

    detailTitle.textContent = task.title;

    detailMeta.innerHTML = `
        <span class="task-detail-status task-detail-status--${escHtml(task.status)}">
            ${escHtml(STATUS_LABELS[task.status] || task.status)}
        </span>
        ${task.assignee
            ? `<span class="task-detail-assignee">${escHtml(task.assignee)}</span>`
            : ''}
    `;

    detailDesc.textContent = task.description || '';
    detailNotes.value      = '';

    detailModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    await loadComments(task.id);
    detailNotes.focus();
}

function closeDetailModal() {
    detailModal.classList.add('hidden');
    document.body.style.overflow = '';
    detailTaskId = null;
}

detailClose?.addEventListener('click',  closeDetailModal);
detailCancel?.addEventListener('click', closeDetailModal);

detailModal?.addEventListener('click', (e) => {
    if (e.target === detailModal) closeDetailModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !detailModal?.classList.contains('hidden')) {
        closeDetailModal();
    }
});

detailSaveBtn?.addEventListener('click', async () => {
    if (detailTaskId === null) return;
    const body = detailNotes.value.trim();
    if (!body) return;

    detailSaveBtn.disabled    = true;
    detailSaveBtn.textContent = 'Posting…';
    try {
        await fetch(COMMENTS_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ task_id: detailTaskId, body, author: 'G' }),
        });
        detailNotes.value = '';
        await loadComments(detailTaskId);
    } catch (err) {
        console.error('Failed to post comment:', err);
    } finally {
        detailSaveBtn.disabled    = false;
        detailSaveBtn.textContent = 'Post Comment';
    }
});

/* ---- Init ---- */
loadTasks();
