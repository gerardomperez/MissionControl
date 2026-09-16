-- ============================================================================
-- Mission Control — SQLite Schema
-- OpenClaw Agent Operations Dashboard
-- ============================================================================
-- Run: sqlite3 /var/www/mission-control/db/mission_control.db < schema.sql
-- ============================================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ============================================================================
-- 1. ORCHESTRATOR — Kanban Tasks
-- ============================================================================

CREATE TABLE IF NOT EXISTS tasks (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           TEXT    NOT NULL,
    description     TEXT    DEFAULT '',
    status          TEXT    NOT NULL DEFAULT 'backlog'
                        CHECK (status IN ('backlog','on_deck','in_progress','blocked','review','done')),
    assignee        TEXT    DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now')),
    status_changed_at TEXT  NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now')),
    hidden          INTEGER NOT NULL DEFAULT 0,
    sort_order      INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX idx_tasks_status ON tasks(status);
CREATE INDEX idx_tasks_hidden ON tasks(hidden);

-- ============================================================================
-- 2. CALENDAR — Scheduled Events
-- ============================================================================

CREATE TABLE IF NOT EXISTS calendar_events (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           TEXT    NOT NULL,
    description     TEXT    DEFAULT '',
    scheduled_at    TEXT    NOT NULL,   -- ISO-8601 datetime: "2026-03-10T09:00:00"
    event_status    TEXT    NOT NULL DEFAULT 'pending'
                        CHECK (event_status IN ('pending','success','failed')),
    source          TEXT    DEFAULT '',  -- e.g. "heartbeat", "cron", "manual"
    cron_expression TEXT    DEFAULT '',  -- original cron if auto-generated
    created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
);

CREATE INDEX idx_events_scheduled ON calendar_events(scheduled_at);
CREATE INDEX idx_events_status    ON calendar_events(event_status);

-- ============================================================================
-- 3. STATUS LOG — Status Reports
-- ============================================================================

CREATE TABLE IF NOT EXISTS status_reports (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           TEXT    NOT NULL,
    body            TEXT    NOT NULL DEFAULT '',   -- Markdown or plain text
    source_agent    TEXT    DEFAULT '',             -- Which agent produced it
    created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
);

CREATE INDEX idx_reports_created ON status_reports(created_at);

-- ============================================================================
-- 4. TEAM — Subagent Registry
-- ============================================================================

CREATE TABLE IF NOT EXISTS subagents (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL UNIQUE,
    role            TEXT    NOT NULL DEFAULT '',
    description     TEXT    DEFAULT '',
    specializations TEXT    DEFAULT '',   -- Comma-separated or JSON array
    status          TEXT    NOT NULL DEFAULT 'idle'
                        CHECK (status IN ('active','idle','standby','offline')),
    avatar_emoji    TEXT    DEFAULT '🤖',
    created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
);

-- ============================================================================
-- 5. SEED DATA — Subagent Fleet
-- ============================================================================

INSERT INTO subagents (name, role, description, specializations, status, avatar_emoji) VALUES
    ('Henry',    'Orchestrator',        'Primary orchestrator agent. Manages all subagents, routes tasks, monitors system health, and coordinates cross-vertical workflows.', 'task routing, workflow orchestration, system diagnostics, session management', 'active', '🧠'),
    ('Cody',     'Copywriter',          'Produces written content across all business verticals — emails, landing pages, blog posts, ad copy, and product descriptions.', 'email sequences, SEO copy, brand voice, long-form content', 'active', '✍️'),
    ('Rhea',     'Creative Director',   'Oversees visual identity, brand guidelines, creative briefs, and design direction for all projects.', 'brand strategy, visual identity, creative briefs, photography direction', 'active', '🎨'),
    ('Mark',     'Marketing',           'Plans and executes marketing campaigns, manages social media scheduling, tracks campaign metrics, and handles audience segmentation.', 'campaign planning, social media, analytics, audience segmentation', 'idle', '📣'),
    ('Sal',      'Sales',               'Handles sales funnel strategy, outreach sequences, lead qualification, and conversion optimization.', 'lead generation, outreach, funnel optimization, CRM management', 'idle', '💼'),
    ('LaQuesha', 'Quality Assurance',   'Reviews all deliverables for accuracy, consistency, brand compliance, and technical correctness before they ship.', 'copy review, bug testing, compliance checks, style enforcement', 'active', '🔍'),
    ('Dev',      'Development',         'Builds and maintains codebases, deploys services, patches bugs, and handles all technical implementation.', 'full-stack development, API integration, deployment, debugging', 'active', '⚙️'),
    ('Cass',     'Customer Support',    'Manages customer communication, support tickets, FAQ maintenance, and satisfaction tracking.', 'ticket triage, FAQ writing, customer communication, escalation', 'idle', '💬'),
    ('Her',      'Human Resources',     'Handles team coordination, onboarding documentation, role definitions, and internal process documentation.', 'onboarding, process docs, role definitions, team coordination', 'idle', '👥'),
    ('Gal',      'Legal & Compliance',  'Reviews content for legal compliance, manages terms of service, privacy policies, and regulatory adherence.', 'compliance review, TOS drafting, privacy policy, regulatory guidance', 'idle', '⚖️'),
    ('Fin',      'Finance',             'Tracks budgets, monitors expenses, produces financial reports, and manages billing integrations.', 'budget tracking, expense reports, financial forecasting, billing', 'idle', '📊'),
    ('Otto',     'Operations & Automation', 'Manages n8n workflows, monitors system automation, handles DevOps tasks, and optimizes recurring processes.', 'n8n workflows, CI/CD, process automation, monitoring', 'active', '🔧'),
    ('Aria',     'Data Analytics',      'Analyzes performance data, produces reports and dashboards, identifies trends, and supports data-driven decisions.', 'data analysis, reporting, dashboards, trend identification', 'standby', '📈'),
    ('Kai',      'Knowledge Management','Maintains MEMORY.md, LEARNINGS.md, and all institutional knowledge files. Manages the QMD semantic search index.', 'knowledge base, documentation, semantic search, memory consolidation', 'active', '📚'),
    ('Tara',     'Research',            'Conducts market research, competitor analysis, technology scouting, and produces research briefs.', 'market research, competitor analysis, tech scouting, trend reports', 'active', '🔬'),
    ('Mira',     'UX & Design',         'Creates wireframes, UI mockups, user flow diagrams, and handles frontend design implementation.', 'wireframing, UI design, user flows, accessibility', 'idle', '🖌️'),
    ('Petra',    'Project Management',  'Tracks project timelines, manages dependencies, produces status updates, and coordinates cross-team deliverables.', 'project tracking, timeline management, dependency mapping, status reports', 'standby', '📋');

-- ============================================================================
-- 6. SEED DATA — Sample Kanban Tasks
-- ============================================================================

INSERT INTO tasks (title, description, status, assignee, status_changed_at, sort_order) VALUES
    ('Bonavita email sequence #5',       'Draft tallow benefits email for subscriber nurture series',                     'in_progress', 'Cody',     strftime('%Y-%m-%dT%H:%M:%S','now','-2 hours'),   1),
    ('Fix /api/subscribe CORS headers',  'Resolve cross-origin issue on Bonavita signup endpoint',                        'done',        'Dev',      strftime('%Y-%m-%dT%H:%M:%S','now','-5 hours'),   1),
    ('SendGrid DNS verification',        'Verify DKIM and SPF records on GoDaddy for bonavita domain',                    'blocked',     'Otto',     strftime('%Y-%m-%dT%H:%M:%S','now','-1 day'),     1),
    ('Competitor skincare audit',         'Research top 10 tallow-based skincare competitors — pricing, positioning, SKUs','review',      'Tara',     strftime('%Y-%m-%dT%H:%M:%S','now','-3 hours'),   1),
    ('Blog integration at /blog',        'Set up blog route on Bonavita site with SEO-optimized templates',               'backlog',     '',         strftime('%Y-%m-%dT%H:%M:%S','now','-2 days'),    1),
    ('QMD index rebuild automation',     'Create n8n workflow to trigger weekly QMD reindex via cron',                     'backlog',     'Otto',     strftime('%Y-%m-%dT%H:%M:%S','now','-1 day'),     2),
    ('Product photography briefs',       'Creative briefs for tallow balm line — 4 SKU shots needed',                     'in_progress', 'Rhea',     strftime('%Y-%m-%dT%H:%M:%S','now','-6 hours'),   2),
    ('Social post batch — week 12',      'Draft Mon/Wed/Fri social content for Bonavita channels',                        'review',      'Mark',     strftime('%Y-%m-%dT%H:%M:%S','now','-4 hours'),   2);

-- ============================================================================
-- 7. SEED DATA — Sample Calendar Events
-- ============================================================================

INSERT INTO calendar_events (title, scheduled_at, event_status, source, cron_expression) VALUES
    ('Service Health Ping',        strftime('%Y-%m-%dT08:00:00','now','-3 days'), 'success', 'heartbeat', '*/15 * * * *'),
    ('Token Budget Audit',         strftime('%Y-%m-%dT12:00:00','now','-2 days'), 'success', 'heartbeat', '0 */2 * * *'),
    ('Bonavita Email Digest',      strftime('%Y-%m-%dT09:00:00','now','-1 day'),  'success', 'cron',      '0 9 * * 1-5'),
    ('n8n Workflow Sweep',         strftime('%Y-%m-%dT03:30:00','now','-1 day'),  'failed',  'cron',      '30 3 * * *'),
    ('Session Hygiene Check',      strftime('%Y-%m-%dT%H:%M:%S','now'),           'success', 'heartbeat', '*/30 * * * *'),
    ('Disk Usage Report',          strftime('%Y-%m-%dT05:00:00','now','+1 day'),  'pending',  'cron',      '0 5 * * *'),
    ('SendGrid Queue Flush',      strftime('%Y-%m-%dT12:00:00','now','+1 day'),  'pending',  'cron',      '0 */6 * * *'),
    ('Memory.md Consolidation',   strftime('%Y-%m-%dT02:00:00','now','+3 days'), 'pending',  'cron',      '0 2 * * 0'),
    ('Subagent Prompt Audit',     strftime('%Y-%m-%dT01:00:00','now','+5 days'), 'pending',  'cron',      '0 1 * * 6'),
    ('QMD Index Rebuild',         strftime('%Y-%m-%dT04:00:00','now','+7 days'), 'pending',  'cron',      '0 4 * * 1'),
    ('Backup Verification',       strftime('%Y-%m-%dT06:00:00','now','+2 days'), 'pending',  'heartbeat', '0 6 * * *'),
    ('Service Health Ping',       strftime('%Y-%m-%dT08:00:00','now','+4 days'), 'pending',  'heartbeat', '*/15 * * * *'),
    ('Git Auto-Commit Learnings', strftime('%Y-%m-%dT23:00:00','now','+1 day'),  'pending',  'cron',      '0 23 * * *'),
    ('Bonavita Email Digest',     strftime('%Y-%m-%dT09:00:00','now','+2 days'), 'pending',  'cron',      '0 9 * * 1-5'),
    ('Ollama Embedding Sync',     strftime('%Y-%m-%dT16:00:00','now','+3 days'), 'pending',  'heartbeat', '0 */4 * * *');

-- ============================================================================
-- 8. SEED DATA — Sample Status Reports
-- ============================================================================

INSERT INTO status_reports (title, body, source_agent, created_at) VALUES
    ('Daily Ops Summary — ' || date('now','-1 day'),
     '## Daily Operations Summary' || char(10) || char(10) ||
     '**Date:** ' || date('now','-1 day') || char(10) ||
     '**Reporting Agent:** Henry' || char(10) || char(10) ||
     '### Tasks Completed' || char(10) ||
     '- Fixed /api/subscribe CORS headers (Dev)' || char(10) ||
     '- Drafted Bonavita email #4 in tallow series (Cody)' || char(10) ||
     '- Indexed 3 competitor research reports (Tara)' || char(10) || char(10) ||
     '### Issues' || char(10) ||
     '- SendGrid DNS verification still blocked — awaiting GoDaddy propagation' || char(10) ||
     '- LaQuesha flagged 2 copy issues in product page — returned to Cody' || char(10) || char(10) ||
     '### System Health' || char(10) ||
     '- CPU: 23% avg | RAM: 61% | Disk: 44%' || char(10) ||
     '- All services nominal. No session corruption events.' || char(10) ||
     '- Token usage: 124.7k / 250k daily budget (49.9%)',
     'Henry',
     strftime('%Y-%m-%dT18:00:00','now','-1 day')),

    ('QA Report — Bonavita Product Pages',
     '## QA Report: Bonavita Product Page Copy' || char(10) || char(10) ||
     '**Reviewed by:** LaQuesha' || char(10) ||
     '**Date:** ' || date('now','-1 day') || char(10) || char(10) ||
     '### Findings' || char(10) ||
     '1. **Tallow Balm — Hero Section:** CTA button text reads "Buy now" but should match brand voice: "Try Bonavita"' || char(10) ||
     '2. **Ingredients Panel:** Missing INCI name for jojoba oil (Simmondsia Chinensis Seed Oil)' || char(10) || char(10) ||
     '### Recommendation' || char(10) ||
     'Return to Cody for revisions. Both issues are minor — estimated 15 min fix.',
     'LaQuesha',
     strftime('%Y-%m-%dT14:30:00','now','-1 day')),

    ('Weekly Token Budget Analysis',
     '## Token Budget Report — Week of ' || date('now','-7 days') || char(10) || char(10) ||
     '**Analyst:** Aria' || char(10) || char(10) ||
     '### Summary' || char(10) ||
     '- **Total tokens consumed:** 847,200 / 1,750,000 weekly budget (48.4%)' || char(10) ||
     '- **Highest consumer:** Henry orchestrator loop (312k — identity file loads)' || char(10) ||
     '- **Lowest consumer:** Fin (unused this week)' || char(10) || char(10) ||
     '### Recommendations' || char(10) ||
     '- Implement progressive disclosure for SOUL.md / AGENTS.md to reduce per-message overhead' || char(10) ||
     '- Consider QMD semantic lookup for reference files instead of full loads' || char(10) ||
     '- Current trajectory: on track to stay under budget through end of month',
     'Aria',
     strftime('%Y-%m-%dT10:00:00','now','-3 days'));
