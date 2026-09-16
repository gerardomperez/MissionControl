<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Use the main henry.db database (single source of truth, see
        // Henry/claude-code-db-consolidation-prompt.md)
        $dbPath = __DIR__ . '/../../henry.db';
        
        // Check if we can write to the directory (needed for WAL mode)
        $dbDir = dirname($dbPath);
        $canWriteDir = is_writable($dbDir);
        
        // If we can't write to the directory, copy the DB to /tmp for this request
        // This is a workaround for when PHP runs as www-data
        if (!$canWriteDir && !is_writable($dbPath)) {
            // Try to use the database in read-only mode first
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            // Don't set WAL mode - use default (DELETE) which works with readonly
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA query_only = ON');
        } else {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // WAL mode requires creating -wal and -shm files in the DB directory.
            // If the directory is not writable (e.g. www-data serving from clawd home),
            // use DELETE journal mode instead — it only writes to the DB file itself.
            if ($canWriteDir) {
                try {
                    $pdo->exec('PRAGMA journal_mode = WAL');
                } catch (Exception $e) {
                    $pdo->exec('PRAGMA journal_mode = DELETE');
                }
            } else {
                // Force DELETE mode; WAL would fail silently then error on first write
                $pdo->exec('PRAGMA journal_mode = DELETE');
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        
        // Ensure Mission Control specific tables/columns exist
        // Migration: add hidden and sort_order to tasks if not present
        try {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0");
        } catch (Exception $e) { /* Column already exists */ }
        try {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0");
        } catch (Exception $e) { /* Column already exists */ }

        // Migration: remove CHECK constraint that excludes 'on_deck' status.
        // The original schema had CHECK(status IN ('backlog','in_progress','blocked','review','done'))
        // which silently rejects on_deck updates. Recreate the table without any CHECK constraint.
        $taskDdl = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='tasks'")->fetchColumn();
        if ($taskDdl !== false && stripos($taskDdl, 'CHECK') !== false && strpos($taskDdl, "'on_deck'") === false) {
            $cols = array_column(
                $pdo->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $keep = ['id','title','description','status','assignee','created_at',
                     'status_changed_at','hidden','sort_order','project_id','predecessor_task_id'];
            $common = implode(', ', array_map(
                fn($c) => "\"$c\"",
                array_values(array_intersect($cols, $keep))
            ));
            $pdo->exec('PRAGMA foreign_keys=OFF');
            $pdo->exec('BEGIN TRANSACTION');
            try {
                $pdo->exec('DROP TABLE IF EXISTS _tasks_backup');
                $pdo->exec('ALTER TABLE tasks RENAME TO _tasks_backup');
                $pdo->exec("CREATE TABLE tasks (
                    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                    title               TEXT    NOT NULL,
                    description         TEXT    DEFAULT '',
                    status              TEXT    NOT NULL DEFAULT 'backlog',
                    assignee            TEXT    DEFAULT '',
                    created_at          TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now')),
                    status_changed_at   TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now')),
                    hidden              INTEGER NOT NULL DEFAULT 0,
                    sort_order          INTEGER NOT NULL DEFAULT 0,
                    project_id          INTEGER DEFAULT NULL,
                    predecessor_task_id INTEGER DEFAULT NULL
                )");
                $pdo->exec("INSERT INTO tasks ($common) SELECT $common FROM _tasks_backup");
                $pdo->exec('DROP TABLE _tasks_backup');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_hidden ON tasks(hidden)');
                $pdo->exec('COMMIT');
            } catch (Exception $ex) {
                $pdo->exec('ROLLBACK');
                try { $pdo->exec('ALTER TABLE _tasks_backup RENAME TO tasks'); } catch (Exception $e2) {}
            }
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
        
        // Migration: add missing columns to calendar_events
        try {
            $pdo->exec("ALTER TABLE calendar_events ADD COLUMN description TEXT DEFAULT ''");
        } catch (Exception $e) { /* Column already exists */ }
        try {
            $pdo->exec("ALTER TABLE calendar_events ADD COLUMN cron_expression TEXT DEFAULT ''");
        } catch (Exception $e) { /* Column already exists */ }
        try {
            $pdo->exec("ALTER TABLE calendar_events ADD COLUMN model TEXT DEFAULT ''");
        } catch (Exception $e) { /* Column already exists */ }
        
        // Migration: add source_agent to status_reports
        try {
            $pdo->exec("ALTER TABLE status_reports ADD COLUMN source_agent TEXT DEFAULT ''");
        } catch (Exception $e) { /* Column already exists */ }
        
        // Migration: create subagents table if not present
        $pdo->exec("CREATE TABLE IF NOT EXISTS subagents (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            name            TEXT    NOT NULL UNIQUE,
            role            TEXT    NOT NULL DEFAULT '',
            description     TEXT    DEFAULT '',
            specializations TEXT    DEFAULT '',
            status          TEXT    NOT NULL DEFAULT 'idle'
                CHECK (status IN ('active','idle','standby','offline')),
            avatar_emoji    TEXT    DEFAULT '🤖',
            created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
        )");
        
        // Migration: add model column to subagents if not present
        try {
            $pdo->exec("ALTER TABLE subagents ADD COLUMN model TEXT DEFAULT ''");
        } catch (Exception $e) { /* Column already exists */ }
        
        // Migration: add predecessor_task_id to tasks
        try {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN predecessor_task_id INTEGER DEFAULT NULL");
        } catch (Exception $e) { /* Column already exists */ }

        // Migration: create task_comments table if not present
        $pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id    INTEGER NOT NULL,
            author     TEXT    NOT NULL DEFAULT 'G',
            body       TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_task_comments_task ON task_comments(task_id)");

        // Migration: create journal_entries table if not present
        $pdo->exec("CREATE TABLE IF NOT EXISTS journal_entries (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            content    TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
        )");

        // Seed active projects if none exist
        $pdo->exec("INSERT OR IGNORE INTO projects (name, status, category) VALUES
            ('AI Hustle',       'active', 'active'),
            ('Advanced Medias', 'active', 'active'),
            ('Bonavita',        'active', 'active'),
            ('Personal',        'active', 'active'),
            ('Second World',    'active', 'active'),
            ('OwnYourCase',     'active', 'active')
        ");

        // Migration: ensure Kids project exists
        $pdo->exec("INSERT OR IGNORE INTO projects (name, status, category) VALUES ('Kids', 'active', 'active')");
    }
    return $pdo;
}
