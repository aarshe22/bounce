<?php
// Database initialization and management

require_once 'config.php';

function initDatabase() {
    $db = new SQLite3(DB_FILE);
    
    // Create mailboxes table
    $db->exec("CREATE TABLE IF NOT EXISTS mailboxes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        host TEXT NOT NULL,
        port INTEGER DEFAULT 993,
        username TEXT NOT NULL,
        password TEXT NOT NULL,
        inbox_folder TEXT DEFAULT 'INBOX',
        skipped_folder TEXT DEFAULT 'SKIPPED',
        processed_folder TEXT DEFAULT 'PROCESSED',
        problem_folder TEXT DEFAULT 'PROBLEM',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create activity log table
    $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        message TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        mailbox_id INTEGER,
        FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id)
    )");
    
    // Create test mode settings table
    $db->exec("CREATE TABLE IF NOT EXISTS test_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enabled BOOLEAN DEFAULT 0,
        recipients TEXT
    )");
    
    // Insert default test setting if not exists
    $result = $db->query('SELECT COUNT(*) FROM test_settings');
    if ($result->fetchArray(SQLITE3_NUM)[0] == 0) {
        $db->exec("INSERT INTO test_settings (enabled, recipients) VALUES (0, '')");
    }
    
    return $db;
}

function getMailboxes($db) {
    $stmt = $db->prepare('SELECT * FROM mailboxes ORDER BY created_at DESC');
    $result = $stmt->execute();
    
    $mailboxes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $mailboxes[] = $row;
    }
    
    return $mailboxes;
}

function getMailbox($db, $id) {
    $stmt = $db->prepare('SELECT * FROM mailboxes WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    return $result->fetchArray(SQLITE3_ASSOC);
}

function createMailbox($db, $data) {
    $stmt = $db->prepare('INSERT INTO mailboxes (name, host, port, username, password, inbox_folder, skipped_folder, processed_folder, problem_folder) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
    $stmt->bindValue(2, $data['host'], SQLITE3_TEXT);
    $stmt->bindValue(3, $data['port'], SQLITE3_INTEGER);
    $stmt->bindValue(4, $data['username'], SQLITE3_TEXT);
    $stmt->bindValue(5, $data['password'], SQLITE3_TEXT);
    $stmt->bindValue(6, $data['inbox_folder'], SQLITE3_TEXT);
    $stmt->bindValue(7, $data['skipped_folder'], SQLITE3_TEXT);
    $stmt->bindValue(8, $data['processed_folder'], SQLITE3_TEXT);
    $stmt->bindValue(9, $data['problem_folder'], SQLITE3_TEXT);
    
    return $stmt->execute();
}

function updateMailbox($db, $id, $data) {
    $stmt = $db->prepare('UPDATE mailboxes SET name = ?, host = ?, port = ?, username = ?, password = ?, inbox_folder = ?, skipped_folder = ?, processed_folder = ?, problem_folder = ? WHERE id = ?');
    $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
    $stmt->bindValue(2, $data['host'], SQLITE3_TEXT);
    $stmt->bindValue(3, $data['port'], SQLITE3_INTEGER);
    $stmt->bindValue(4, $data['username'], SQLITE3_TEXT);
    $stmt->bindValue(5, $data['password'], SQLITE3_TEXT);
    $stmt->bindValue(6, $data['inbox_folder'], SQLITE3_TEXT);
    $stmt->bindValue(7, $data['skipped_folder'], SQLITE3_TEXT);
    $stmt->bindValue(8, $data['processed_folder'], SQLITE3_TEXT);
    $stmt->bindValue(9, $data['problem_folder'], SQLITE3_TEXT);
    $stmt->bindValue(10, $id, SQLITE3_INTEGER);
    
    return $stmt->execute();
}

function deleteMailbox($db, $id) {
    $stmt = $db->prepare('DELETE FROM mailboxes WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    
    return $stmt->execute();
}

function getTestSettings($db) {
    $stmt = $db->prepare('SELECT * FROM test_settings LIMIT 1');
    $result = $stmt->execute();
    
    return $result->fetchArray(SQLITE3_ASSOC);
}

function updateTestSettings($db, $enabled, $recipients) {
    $stmt = $db->prepare('UPDATE test_settings SET enabled = ?, recipients = ? WHERE id = 1');
    $stmt->bindValue(1, $enabled, SQLITE3_INTEGER);
    $stmt->bindValue(2, $recipients, SQLITE3_TEXT);
    
    return $stmt->execute();
}

function logActivity($db, $type, $message, $mailbox_id = null) {
    $stmt = $db->prepare('INSERT INTO activity_log (type, message, mailbox_id) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $type, SQLITE3_TEXT);
    $stmt->bindValue(2, $message, SQLITE3_TEXT);
    $stmt->bindValue(3, $mailbox_id, SQLITE3_INTEGER);
    
    return $stmt->execute();
}

function getActivityLog($db, $limit = 50, $offset = 0, $type_filter = null) {
    $sql = "SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params = [$limit, $offset];
    
    if ($type_filter) {
        $sql = "SELECT * FROM activity_log WHERE type = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $params = [$type_filter, $limit, $offset];
    }
    
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_int($param) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    
    $logs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    
    return $logs;
}

function getActivityLogCount($db, $type_filter = null) {
    $sql = "SELECT COUNT(*) FROM activity_log";
    $params = [];
    
    if ($type_filter) {
        $sql = "SELECT COUNT(*) FROM activity_log WHERE type = ?";
        $params = [$type_filter];
    }
    
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param, is_int($param) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_NUM)[0];
}

?>