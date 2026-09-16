<?php
/* Orchestrator — Kanban Board */
require_once __DIR__ . '/../includes/db.php';
$pdo      = getDB();
$projects = $pdo->query("SELECT id, name FROM projects WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$agents   = $pdo->query("SELECT name FROM subagents ORDER BY name ASC")->fetchAll();
?>

<div class="kanban-wrapper">

    <!-- Page Header -->
    <div class="kanban-header">
        <h1>Orchestrator</h1>
        <!-- Project Filter Dropdown - Centered -->
        <div class="project-filter">
            <label for="projectFilter" class="filter-label">Filter by Project:</label>
            <select id="projectFilter" class="filter-select">
                <option value="all">All Projects</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" id="newTaskBtn">+ New Task</button>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-board" id="kanbanBoard">

        <!-- Backlog -->
        <div class="kanban-col" data-status="backlog">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--text-muted)"></span>
                <span class="col-label">Backlog</span>
                <span class="col-count" id="count-backlog">0</span>
            </div>
            <div class="kanban-cards" id="col-backlog"></div>
        </div>

        <!-- On Deck -->
        <div class="kanban-col" data-status="on_deck">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--pending)"></span>
                <span class="col-label">On Deck</span>
                <span class="col-count" id="count-on_deck">0</span>
            </div>
            <div class="kanban-cards" id="col-on_deck"></div>
        </div>

        <!-- In Progress -->
        <div class="kanban-col" data-status="in_progress">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--info)"></span>
                <span class="col-label">In Progress</span>
                <span class="col-count" id="count-in_progress">0</span>
            </div>
            <div class="kanban-cards" id="col-in_progress"></div>
        </div>

        <!-- Blocked -->
        <div class="kanban-col" data-status="blocked">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--danger)"></span>
                <span class="col-label">Blocked</span>
                <span class="col-count" id="count-blocked">0</span>
            </div>
            <div class="kanban-cards" id="col-blocked"></div>
        </div>

        <!-- Review -->
        <div class="kanban-col" data-status="review">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--warning)"></span>
                <span class="col-label">Ready for Review</span>
                <span class="col-count" id="count-review">0</span>
            </div>
            <div class="kanban-cards" id="col-review"></div>
        </div>

        <!-- Done -->
        <div class="kanban-col" data-status="done">
            <div class="kanban-col-header">
                <span class="col-dot" style="background:var(--success)"></span>
                <span class="col-label">Done</span>
                <span class="col-count" id="count-done">0</span>
                <button class="col-toggle-btn" id="doneToggleBtn">&#128065; Hide</button>
            </div>
            <div class="kanban-cards" id="col-done"></div>
        </div>

    </div>
</div>

<!-- Task Detail Modal -->
<div class="modal-backdrop hidden" id="taskDetailModal">
    <div class="modal modal--task-detail" role="dialog" aria-modal="true" aria-labelledby="taskDetailTitle">
        <div class="modal-header task-detail-header">
            <div class="task-detail-meta" id="taskDetailMeta"></div>
            <h2 id="taskDetailTitle"></h2>
            <button class="modal-close" id="taskDetailClose" aria-label="Close">&#10005;</button>
        </div>
        <div class="modal-body task-detail-body">
            <!-- Project -->
            <div class="task-detail-section">
                <label class="task-detail-label" for="taskDetailProject">Project</label>
                <select id="taskDetailProject" class="form-control">
                    <option value="">— No Project —</option>
                </select>
            </div>

            <div id="taskDetailDesc" class="task-detail-desc"></div>

            <!-- Comment history log -->
            <div class="task-detail-section" id="commentLogSection">
                <div class="task-detail-label">Comment History</div>
                <div id="commentLog" class="comment-log">
                    <div class="comment-log-empty">No comments yet.</div>
                </div>
            </div>

            <!-- Status change dropdown -->
            <div class="task-detail-section">
                <label class="task-detail-label" for="taskStatusChange">Change Status</label>
                <select id="taskStatusChange" class="form-control">
                    <option value="backlog">Backlog</option>
                    <option value="on_deck">On Deck</option>
                    <option value="in_progress">In Progress</option>
                    <option value="blocked">Blocked</option>
                    <option value="review">Ready for Review</option>
                    <option value="done">Done</option>
                </select>
            </div>

            <!-- New comment input -->
            <div class="task-detail-section">
                <label class="task-detail-label" for="taskDetailNotes">Add Comment</label>
                <textarea id="taskDetailNotes" class="form-control task-detail-notes" rows="3" placeholder="Write a comment…"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" id="taskDetailCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="taskDetailSave">Post Comment</button>
            </div>
        </div>
    </div>
</div>

<!-- New Task Modal -->
<div class="modal-backdrop hidden" id="newTaskModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="newTaskModalTitle">
        <div class="modal-header">
            <h2 id="newTaskModalTitle">New Task</h2>
            <button class="modal-close" id="newTaskModalClose" aria-label="Close">&#10005;</button>
        </div>
        <div class="modal-body">
            <form id="newTaskForm">
                <div class="form-group">
                    <label for="taskProject">Project</label>
                    <select id="taskProject" class="form-control">
                        <option value="">— No Project —</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="taskTitle">Title <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="taskTitle" class="form-control" placeholder="What needs to be done?" required>
                </div>
                <div class="form-group">
                    <label for="taskDesc">Description</label>
                    <textarea id="taskDesc" class="form-control" rows="3" placeholder="Optional details..."></textarea>
                </div>
                <div class="form-group">
                    <label for="taskAssignee">Assignee</label>
                    <select id="taskAssignee" class="form-control">
                        <option value="Me" selected>Me</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="taskStatus">Status</label>
                    <select id="taskStatus" class="form-control">
                        <option value="backlog">Backlog</option>
                        <option value="on_deck">On Deck</option>
                        <option value="in_progress">In Progress</option>
                        <option value="blocked">Blocked</option>
                        <option value="review">Ready for Review</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="taskPredecessor">Predecessor Task</label>
                    <select id="taskPredecessor" class="form-control">
                        <option value="">— None —</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="newTaskCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
