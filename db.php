<?php
// Database configuration
define('DB_FILE', 'database.sqlite');

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $db->exec("CREATE TABLE IF NOT EXISTS mailboxes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        host TEXT NOT NULL,
        port INTEGER NOT NULL,
        username TEXT NOT NULL,
        password TEXT NOT NULL,
        inbox_folder TEXT DEFAULT 'INBOX',
        processed_folder TEXT DEFAULT 'Processed',
        skipped_folder TEXT DEFAULT 'Skipped'
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        message TEXT NOT NULL,
        mailbox_id INTEGER,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS test_settings (
        id INTEGER PRIMARY KEY,
        enabled INTEGER DEFAULT 0,
        recipients TEXT
    )");
    
    $db->exec("INSERT OR IGNORE INTO test_settings (id, enabled, recipients) VALUES (1, 0, '')");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Make sure the database file exists and is writable
if (!file_exists(DB_FILE)) {
    // Try to create it
    touch(DB_FILE);
    chmod(DB_FILE, 0666);
}
?>