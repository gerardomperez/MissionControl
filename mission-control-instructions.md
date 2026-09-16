# Mission Control — Build Instructions for Claude Code

> **OpenClaw Agent Operations Dashboard**
> A four-page PHP microsite with SQLite backend for monitoring Henry and the subagent fleet.

---

## Overview

Mission Control is a lightweight dashboard that runs on the Hostinger VPS alongside the OpenClaw agent system. It provides a web interface for tracking tasks, viewing scheduled events, reading status reports, and inspecting the subagent team.

### Tech Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Language   | PHP 8.x                             |
| Database   | SQLite 3 (via PDO)                  |
| Frontend   | Vanilla HTML / CSS / JS (no build)  |
| Server     | nginx (already running on VPS)      |
| Theme      | Light color scheme                  |

### Directory Structure

```
/var/www/mission-control/
├── db/
│   ├── schema.sql              ← Database schema + seed data
│   └── mission_control.db      ← SQLite database (created by schema)
├── public/
│   ├── index.php               ← Router / entry point
│   ├── css/
│   │   └── style.css           ← Global stylesheet (light theme)
│   └── js/
│       ├── kanban.js           ← Orchestrator page logic
│       ├── calendar.js         ← Calendar page logic
│       ├── statuslog.js        ← Status Log page logic
│       └── team.js             ← Team page logic
├── includes/
│   ├── db.php                  ← PDO connection singleton
│   ├── header.php              ← Shared HTML head + nav
│   └── footer.php              ← Shared footer + closing tags
├── pages/
│   ├── orchestrator.php        ← Kanban board page
│   ├── calendar.php            ← Calendar grid page
│   ├── statuslog.php           ← Status reports page
│   └── team.php                ← Subagent roster page
├── api/
│   ├── tasks.php               ← REST endpoints for tasks (CRUD + status change)
│   ├── events.php              ← REST endpoints for calendar events
│   ├── reports.php             ← REST endpoints for status reports
│   └── agents.php              ← REST endpoint for subagent list
└── README.md
```

### Global Design Tokens (Light Theme)

Use these values consistently across the entire site. Define them as CSS custom properties in `style.css`:

```css
:root {
    /* Surfaces */
    --bg:             #f8f9fc;
    --surface:        #ffffff;
    --surface-alt:    #f1f3f8;
    --surface-hover:  #e8ecf4;

    /* Borders */
    --border:         #dfe3ec;
    --border-strong:  #c5cdd8;

    /* Text */
    --text:           #1a1d26;
    --text-secondary: #5a6376;
    --text-muted:     #8994a8;

    /* Accent — teal-green (matches OpenClaw identity) */
    --accent:         #0d9f7f;
    --accent-light:   #e6f7f3;
    --accent-dark:    #087a62;

    /* Status colors */
    --success:        #16a34a;
    --success-bg:     #f0fdf4;
    --warning:        #d97706;
    --warning-bg:     #fffbeb;
    --danger:         #dc2626;
    --danger-bg:      #fef2f2;
    --info:           #2563eb;
    --info-bg:        #eff6ff;
    --pending:        #7c3aed;
    --pending-bg:     #f5f3ff;

    /* Typography */
    --font-body:      'IBM Plex Sans', -apple-system, sans-serif;
    --font-mono:      'JetBrains Mono', 'Fira Code', monospace;
    --font-display:   'DM Sans', 'IBM Plex Sans', sans-serif;

    /* Spacing */
    --radius-sm:      6px;
    --radius-md:      10px;
    --radius-lg:      14px;

    /* Shadows */
    --shadow-sm:      0 1px 3px rgba(0,0,0,0.06);
    --shadow-md:      0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg:      0 8px 24px rgba(0,0,0,0.10);
}
```

Import Google Fonts in the HTML `<head>`:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

---

## Foundation — Shared Infrastructure

> Build these files first. Every page depends on them.

### `db/schema.sql`

A separate file `mission-control-schema.sql` is provided alongside this document. Run it to create the database:

```bash
mkdir -p /var/www/mission-control/db
sqlite3 /var/www/mission-control/db/mission_control.db < /path/to/mission-control-schema.sql
chown www-data:www-data /var/www/mission-control/db/mission_control.db
chmod 664 /var/www/mission-control/db/mission_control.db
```

### `includes/db.php`

```php
<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = __DIR__ . '/../db/mission_control.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}
```

### `includes/header.php`

Shared HTML `<head>`, the top navigation bar, and opening body tag. The nav has four links (Orchestrator, Calendar, Status Log, Team). Active page is detected by comparing `$_GET['page']` to highlight the current nav item.

**Nav bar design:**
- Fixed at top, white background, bottom border `var(--border)`.
- Left side: "MC" badge (accent background, white text, rounded square) followed by "Mission Control" in `--font-display` weight 700.
- Right side: four nav links as pill-shaped buttons. Active link gets `var(--accent-light)` background with `var(--accent)` text. Inactive links are `var(--text-secondary)` and get `var(--surface-hover)` on hover.
- Below the nav, add a subtle `var(--shadow-sm)` drop shadow.

### `includes/footer.php`

Closes the content wrapper, includes the page-specific JS file, and closes `</body></html>`. The footer contains a small centered line: "Mission Control — OpenClaw Agent Operations" in `--text-muted` and `--font-mono` at 11px.

### `public/index.php` — Router

Simple front controller. Reads `$_GET['page']` and includes the corresponding file from `pages/`. Default to `orchestrator`. Reject unknown pages with a 404.

```php
<?php
$page = $_GET['page'] ?? 'orchestrator';
$allowed = ['orchestrator', 'calendar', 'statuslog', 'team'];
if (!in_array($page, $allowed)) {
    http_response_code(404);
    echo '404 — Page not found';
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../pages/' . $page . '.php';
require_once __DIR__ . '/../includes/footer.php';
```

### `public/css/style.css`

Contains all the CSS custom properties listed above plus base reset styles, the nav bar styles, shared card/button component styles, and the modal overlay styles. Use a single stylesheet for the whole site — no per-page CSS files.

**Base reset rules to include:** `box-sizing: border-box` on all elements, zero default margin/padding, `font-family: var(--font-body)` on body, `background: var(--bg)` on body, `color: var(--text)`, smooth scrolling.

### nginx Configuration

Add a server block or location block for Mission Control:

```nginx
server {
    listen 80;
    server_name mc.frnd.us;   # or subdomain of choice
    root /var/www/mission-control/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## Page 1 — Orchestrator (Kanban Board)

> **File:** `pages/orchestrator.php` + `public/js/kanban.js`
> **API:** `api/tasks.php`
> **Dependencies:** Foundation files only.

### API Endpoints — `api/tasks.php`

This file handles all task CRUD operations. Route by `$_SERVER['REQUEST_METHOD']`.

| Method   | Action                        | Parameters                                                                 |
|----------|-------------------------------|---------------------------------------------------------------------------|
| `GET`    | List all tasks                | Optional query param `?include_hidden=1` to include done+hidden tasks     |
| `POST`   | Create new task               | JSON body: `{ title, description, assignee }`                             |
| `PATCH`  | Update task status            | JSON body: `{ id, status }` — also updates `status_changed_at` to now    |
| `PATCH`  | Toggle task hidden flag       | JSON body: `{ id, hidden: 1 }` or `{ id, hidden: 0 }`                    |
| `DELETE` | Delete task                   | JSON body: `{ id }`                                                       |

All responses are JSON. Set header `Content-Type: application/json`.

**Important behavior on PATCH with a `status` field:** Always update `status_changed_at` to the current timestamp when the status changes. This is how the Kanban cards show "entered this status" time.

### Page Layout — `pages/orchestrator.php`

The page is a **five-column horizontal Kanban board** that fills the viewport below the nav bar.

#### Column Configuration

| Column Key     | Display Label       | Header Color Accent     |
|----------------|---------------------|-------------------------|
| `backlog`      | Backlog             | `var(--text-muted)`     |
| `in_progress`  | In Progress         | `var(--info)`           |
| `blocked`      | Blocked             | `var(--danger)`         |
| `review`       | Ready for Review    | `var(--warning)`        |
| `done`         | Done                | `var(--success)`        |

#### Column UI

Each column is a vertical strip with:
- **Header:** Column label in `--font-display` weight 600, size 14px. A small colored dot (6×6px circle) to the left of the label using the column's accent color. A task count badge to the right (e.g., "3") in a pill with `var(--surface-alt)` background.
- **Card list area:** Scrollable vertical container. Cards are stacked with 8px gap.
- **Background:** `var(--surface)` with `var(--border)` border, `var(--radius-md)` corners.
- **Min-width:** Each column should be at least 240px. The board scrolls horizontally if the viewport is narrow.

#### Card UI

Each task card displays inside its status column:

- **Container:** White background, `var(--border)` border, `var(--radius-sm)` corners, `var(--shadow-sm)`. On hover, lift to `var(--shadow-md)` and shift border to `var(--border-strong)`. Padding 14px.
- **Title:** `--font-body` weight 600, size 14px, `var(--text)`. Truncate with ellipsis at 2 lines.
- **Description:** `--font-body` weight 400, size 12px, `var(--text-secondary)`. Truncate at 3 lines with ellipsis.
- **Footer row:** Flex row, space-between. Left side: Assignee shown as a small pill — if assignee is set, show the agent's name in `--font-mono` 11px with a colored left border matching agent type. If unassigned, show "Unassigned" in `--text-muted` italic. Right side: Timestamp in `--font-mono` 10px, `--text-muted`. Format: `"Jan 15, 9:13 pm"`.
- **Move control:** A small `⋮` (vertical dots) icon button in the top-right corner of the card. On click, show a dropdown with the five statuses. Clicking a status sends a `PATCH` to the API and moves the card to the new column with a smooth CSS transition.

#### New Task Button

- A `+ New Task` button in the page header area (above the columns, right-aligned).
- On click, opens a modal dialog:
  - **Title** field — text input, required.
  - **Description** field — textarea, optional.
  - **Assignee** field — text input (freeform text for agent name), optional.
  - **Status** field — dropdown defaulting to "Backlog" with all five statuses.
  - **Create** button (accent color) and **Cancel** button (gray outline).
- On submit, POST to the API. On success, close modal and insert the new card into the correct column without a full page reload.

#### "Done" Column — Hide/Show Toggle

- At the top of the **Done** column header, include a toggle button: `👁 Show / Hide Completed`.
- When "hidden" mode is active, tasks in the Done column that have `hidden = 1` are not rendered. The button label changes to "Show Hidden (N)" where N is the count of hidden tasks.
- Each card in the Done column has a small "✕ Hide" button in its footer. Clicking it sends a `PATCH { id, hidden: 1 }` and removes the card from view.
- When the toggle is set to "Show Hidden", all Done tasks are rendered, including hidden ones. Hidden tasks get a slightly dimmed appearance (`opacity: 0.6`).

### JavaScript — `public/js/kanban.js`

- On page load, `GET /api/tasks.php` and render all cards into their columns.
- Implement the move-status dropdown, new-task modal, and hide/show toggle.
- Use `fetch()` for all API calls. No external JS dependencies.
- After any mutation, re-fetch and re-render the affected column(s).
- Format timestamps client-side: parse ISO-8601 `status_changed_at` and format as `"Jan 15, 9:13 pm"` using `Intl.DateTimeFormat` or manual formatting.

---

## Page 2 — Calendar

> **File:** `pages/calendar.php` + `public/js/calendar.js`
> **API:** `api/events.php`
> **Dependencies:** Foundation files only. Can be built independently of Page 1.

### API Endpoints — `api/events.php`

| Method | Action                      | Parameters                                                                 |
|--------|-----------------------------|---------------------------------------------------------------------------|
| `GET`  | List events in date range   | Query params: `?start=YYYY-MM-DD&end=YYYY-MM-DD`                         |
| `POST` | Create new event            | JSON body: `{ title, scheduled_at, source?, cron_expression? }`           |
| `PATCH`| Update event status         | JSON body: `{ id, status }` — accepts `success`, `failed`, `pending`|

**GET behavior:** Return all events where `scheduled_at` falls between `start` (inclusive) and `end` (inclusive). Order by `scheduled_at ASC`.

**Note:** The database column is `status` (not `event_status`). Use `status` in API requests and responses.

### Page Layout — `pages/calendar.php`

The calendar shows a **five-week rolling grid**: one week in the past, the current week, and three weeks into the future. Thirty-five day cells total (7 columns × 5 rows), starting on Monday.

#### Header

- **Title:** "Scheduled Tasks" in `--font-display` weight 700, size 20px.
- **Subtitle:** Shows the date range, e.g., "Mar 2 – Apr 5, 2026" in `--font-mono` 12px, `--text-secondary`.
- **Navigation:** Left/right arrow buttons to shift the entire grid by one week in either direction. A "Today" pill button that resets the view to the default (current week centered in row 2).
- **Legend:** Inline to the right of the title. Three small items:
  - Green dot + "Success"
  - Red dot + "Failed"
  - Purple dot + "Pending"

#### Grid Layout

- **CSS Grid:** 7 columns (Mon–Sun), 5 rows.
- **Column headers:** Day-of-week abbreviations (Mon, Tue, Wed, …) in `--font-mono` 11px uppercase, `--text-muted`, centered.
- **Day cell design:**
  - Background: `var(--surface)`. Border: `var(--border)`.
  - Top-left: Day number in `--font-mono` 13px weight 600. If the day is today, the number sits inside a small circle with `var(--accent)` background and white text.
  - Days in the past week have a very subtle `var(--surface-alt)` background tint.
  - Minimum height: 110px. If events overflow, show a scrollable area inside the cell or a "+N more" link.

#### Event Cards Inside Day Cells

Each event renders as a compact card inside its day cell:

- **Size:** Full width of the cell, 4px left border colored by status.
- **Background:** Status-specific light tint (`--success-bg`, `--danger-bg`, or `--pending-bg`).
- **Content:**
  - **Time:** `--font-mono` 10px, `--text-muted`. Formatted as `"9:00 AM"` or `"3:30 PM"` — extracted from `scheduled_at`.
  - **Title:** `--font-body` 12px weight 500, `--text`. Truncate at 1 line.
  - **Status pill:** Tiny pill in the bottom-right: "Success" (green), "Failed" (red), or "Pending" (purple). `--font-mono` 9px.
- **Left border color:**
  - Success: `var(--success)`
  - Failed: `var(--danger)`
  - Pending: `var(--pending)`
- **Hover:** Show `var(--shadow-sm)` and a tooltip or small popup with the full event title, description, source, and cron expression if available.

#### Past vs. Future Auto-Coloring

The JavaScript should, on render, automatically assign display status:
- Events with `scheduled_at` in the past that have `status = 'pending'` should render with a `--warning` (amber) tint and show "Overdue" instead of "Pending".
- Events in the future should always show "Pending" regardless of database status.
- Only past events can display "Success" or "Failed".

### JavaScript — `public/js/calendar.js`

- On page load, calculate the 5-week window (Monday of 1 week ago through Sunday of 3 weeks ahead).
- `GET /api/events.php?start=...&end=...` and render events into the grid cells by matching `scheduled_at` dates.
- Implement week-shift navigation (update start/end, re-fetch, re-render).
- Implement the "Today" reset button.
- Format times using `Intl.DateTimeFormat` with `hour: 'numeric', minute: '2-digit'` options.

---

## Page 3 — Status Log

> **File:** `pages/statuslog.php` + `public/js/statuslog.js`
> **API:** `api/reports.php`
> **Dependencies:** Foundation files only. Can be built independently of Pages 1–2.

### API Endpoints — `api/reports.php`

| Method | Action                  | Parameters                                            |
|--------|-------------------------|-------------------------------------------------------|
| `GET`  | List report summaries   | Returns `id`, `title`, `source_agent`, `created_at`   |
| `GET`  | Get single report       | Query param: `?id=N` — returns full record with `body`|
| `POST` | Create new report       | JSON body: `{ title, body, source_agent? }`           |

**List behavior (no `id` param):** Return all reports ordered by `created_at DESC`. Do NOT include the `body` field in the list response — only `id`, `title`, `source_agent`, `created_at`. This keeps the list payload light.

**Detail behavior (`?id=N`):** Return the full record including `body`.

### Page Layout — `pages/statuslog.php`

A single-column list of report titles with a detail modal.

#### Header

- **Title:** "Status Reports" in `--font-display` weight 700, size 20px.
- **Subtitle:** "Agent-generated reports and diagnostics" in `--text-secondary` 13px.
- No "create" button on this page — reports are generated by agents via the API and the curl integration pattern.

#### Report List

A vertical stack of report rows. Each row is a clickable card:

- **Container:** `var(--surface)` background, `var(--border)` border, `var(--radius-sm)` corners. Full-width. Padding 16px 20px. On hover: `var(--surface-hover)` background, cursor pointer.
- **Left section (flex: 1):**
  - **Title:** `--font-body` weight 600, 15px, `var(--text)`.
  - **Metadata line:** Below the title. `--font-mono` 11px, `--text-muted`. Shows: source agent name (if present, with a small colored dot) + " · " + formatted date ("Mar 7, 2026, 6:00 PM").
- **Right section:** A small right-arrow chevron (`›`) in `--text-muted`, 18px. Signals clickability.
- **Spacing:** 8px gap between rows.

#### Report Detail Modal

When a report title is clicked, fetch the full report by `id` and display it in a modal overlay.

**Modal design:**
- **Backdrop:** `rgba(0,0,0,0.3)` covering the viewport. Clicking the backdrop closes the modal.
- **Modal container:** Centered, max-width 720px, max-height 80vh, `var(--surface)` background, `var(--radius-lg)` corners, `var(--shadow-lg)`. Overflow-y: auto.
- **Modal header:** Sticky at top. White background. Report title in `--font-display` weight 700, 18px. Below it, a metadata line with source agent and date. A `✕` close button in the top-right corner.
- **Modal body:** Padding 24px 28px. The `body` field may contain Markdown. Render it as-is (preformatted with `white-space: pre-wrap` and `--font-body` 14px). If you want to render Markdown as HTML, include a lightweight Markdown parser — but plain pre-wrapped text is acceptable for v1.
- **Typography inside body:** Headings (`## `) should render bolder and larger. Lists should render with proper indentation. Mono-spaced blocks should use `--font-mono` with a light `var(--surface-alt)` background.

### JavaScript — `public/js/statuslog.js`

- On page load, `GET /api/reports.php` (list mode) and render the row stack.
- On row click, `GET /api/reports.php?id=N` and open the modal with the full body.
- Implement modal open/close (clicking backdrop or ✕ button).
- Optional: a simple client-side Markdown renderer for the body. Minimum viable: replace `## ` lines with `<h3>`, `- ` lines with `<li>`, and wrap backtick blocks in `<code>`. Or use pre-wrap as fallback.

---

## Page 4 — Team

> **File:** `pages/team.php` + `public/js/team.js`
> **API:** `api/agents.php`
> **Dependencies:** Foundation files only. Can be built independently of Pages 1–3.

### API Endpoints — `api/agents.php`

| Method | Action            | Parameters     |
|--------|-------------------|----------------|
| `GET`  | List all agents   | None required  |

Returns all rows from `subagents` ordered by: active agents first, then standby, then idle, then offline. Within each group, order alphabetically by name.

The response is a JSON array. Each object includes: `id`, `name`, `role`, `description`, `specializations`, `status`, `avatar_emoji`.

### Page Layout — `pages/team.php`

A roster page showing all subagents.

#### Header

- **Title:** "The Team" in `--font-display` weight 700, size 20px.
- **Subtitle:** "Henry's subagent fleet — [N] agents" in `--text-secondary` 13px, where N is the total count.
- **Status filter pills:** A row of small toggleable pill buttons: "All", "Active", "Standby", "Idle". Default: "All" selected. Clicking a pill filters the displayed cards. Active pill: `var(--accent)` background, white text. Inactive pills: `var(--surface-alt)` background, `--text-secondary` text.

#### Agent Cards

Display agents as a responsive grid: 3 columns on desktop (>1024px), 2 columns on tablet (>640px), 1 column on mobile.

**Card design:**

- **Container:** `var(--surface)` background, `var(--border)` border, `var(--radius-md)` corners, `var(--shadow-sm)`. Padding 20px. On hover: `var(--shadow-md)`.
- **Top row:** Flex, space-between.
  - **Left:** The `avatar_emoji` at 28px size, followed by the agent `name` in `--font-display` weight 700, 16px, and the `role` in `--font-body` 12px, `--text-secondary` directly below the name.
  - **Right:** A status badge pill.
    - Active: green dot + "Active" in `--success`, background `--success-bg`.
    - Standby: amber dot + "Standby" in `--warning`, background `--warning-bg`.
    - Idle: gray dot + "Idle" in `--text-muted`, background `--surface-alt`.
    - Offline: red dot + "Offline" in `--danger`, background `--danger-bg`.
- **Description:** Below the top row, with 12px top margin. `--font-body` 13px, `--text-secondary`. Line-height 1.5. Show full text — do not truncate.
- **Specializations:** Below description, 10px top margin. Render as a row of small tags/pills that wrap. Each tag: `--font-mono` 10px, `--text-secondary`, `var(--surface-alt)` background, `var(--radius-sm)` corners, padding 3px 8px. Parse the comma-separated `specializations` field to generate each tag.

**Special treatment for Henry:** The first card (Henry, the orchestrator) gets a distinct highlight: a left border of 3px solid `var(--accent)` and a very subtle `var(--accent-light)` background tint, visually distinguishing the primary orchestrator from the subagent fleet.

### JavaScript — `public/js/team.js`

- On page load, `GET /api/agents.php` and render the card grid.
- Implement status filter pills. Filtering is client-side (hide/show cards based on the `status` field).
- No mutation endpoints — this page is read-only in v1.

---

## API Integration — Henry's curl Patterns

Henry (and subagents) can POST data directly to Mission Control's API endpoints from the VPS command line. Include these patterns in Henry's TOOLS.md or in a reference file.

### Create a Task (Kanban)

```bash
curl -s -X POST http://localhost/api/tasks.php \
  -H "Content-Type: application/json" \
  -d '{"title":"Draft email #6","description":"Tallow moisturizer benefits","assignee":"Cody"}'
```

### Move a Task to a New Status

```bash
curl -s -X PATCH http://localhost/api/tasks.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"status":"review"}'
```

### Log a Calendar Event

```bash
curl -s -X POST http://localhost/api/events.php \
  -H "Content-Type: application/json" \
  -d '{"title":"Service Health Ping","scheduled_at":"2026-03-10T08:00:00","source":"heartbeat","cron_expression":"*/15 * * * *"}'
```

### Update Event Outcome (After Execution)

```bash
curl -s -X PATCH http://localhost/api/events.php \
  -H "Content-Type: application/json" \
  -d '{"id":5,"status":"success"}'
```

### Submit a Status Report

```bash
curl -s -X POST http://localhost/api/reports.php \
  -H "Content-Type: application/json" \
  -d '{"title":"Daily Ops Summary — 2026-03-08","body":"## Summary\n\nAll systems nominal.","source_agent":"Henry"}'
```

---

## Deployment Checklist

1. **Create the directory structure** on the VPS:
   ```bash
   mkdir -p /var/www/mission-control/{db,public/{css,js},includes,pages,api}
   ```

2. **Initialize the database:**
   ```bash
   sqlite3 /var/www/mission-control/db/mission_control.db < schema.sql
   chown -R www-data:www-data /var/www/mission-control/db
   ```

3. **Set file permissions:**
   ```bash
   chmod 775 /var/www/mission-control/db
   chmod 664 /var/www/mission-control/db/mission_control.db
   ```

4. **Configure nginx** with the server block from above. Reload:
   ```bash
   sudo nginx -t && sudo systemctl reload nginx
   ```

5. **Verify PHP-FPM** is running and has the `pdo_sqlite` extension:
   ```bash
   php -m | grep pdo_sqlite
   systemctl status php8.2-fpm
   ```

6. **Test the API** with the curl patterns above.

7. **Access the site** at `http://mc.frnd.us` (or whatever domain/subdomain is configured).

---

## Notes for Claude Code

- **No external PHP dependencies.** No Composer. This is a zero-dependency PHP app — just PDO SQLite and vanilla JS.
- **No build step.** No webpack, vite, or npm. Raw files served directly by nginx + PHP-FPM.
- **Each page is self-contained.** The four pages share only the foundation (db.php, header.php, footer.php, style.css). You can build and test each page independently.
- **API-first architecture.** All four pages fetch their data from JSON API endpoints. The PHP page files produce only the HTML shell; JavaScript populates the content. This allows Henry to interact with the same API via curl.
- **Timestamps in the database** are stored as ISO-8601 strings (`strftime('%Y-%m-%dT%H:%M:%S','now')`). All timezone interpretation happens client-side.
- **The database file path** is hardcoded in `db.php` as a relative path. If you move the project root, update that path.
