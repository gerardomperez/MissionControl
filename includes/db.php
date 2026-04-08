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
        // Migration: add notes column if not present
        try {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN notes TEXT NOT NULL DEFAULT ''");
        } catch (Exception $e) {
            // Column already exists — safe to ignore
        }
        // Migration: create task_comments table if not present
        $pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id    INTEGER NOT NULL,
            author     TEXT    NOT NULL DEFAULT 'G',
            body       TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%S','now'))
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_task_comments_task ON task_comments(task_id)");
    }
    return $pdo;
}
