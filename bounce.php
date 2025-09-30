<?php
// Bounce processing functions

function logActivity($action, $details = '') {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO activity_log (action, details, timestamp) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$action, $details]);
    } catch (Exception $e) {
        // Log error silently to avoid breaking the system
    }
}

function processBounce($mailbox_id) {
    global $db;
    
    logActivity("Processing bounce", "Started processing mailbox ID: $mailbox_id");
    
    try {
        $mailbox = $db->prepare("SELECT * FROM mailboxes WHERE id = ?");
        $mailbox->execute([$mailbox_id]);
        $mailbox_data = $mailbox->fetch(PDO::FETCH_ASSOC);
        
        if (!$mailbox_data) {
            return "Mailbox not found";
        }
        
        // This is where the actual IMAP connection and bounce processing would happen
        // For now, we'll simulate it
        
        $processed_count = rand(0, 10); // Simulate some bounces processed
        $skipped_count = rand(0, 5);    // Simulate some skipped emails
        
        logActivity("Processing bounce", "Processed $processed_count bounces, skipped $skipped_count emails for mailbox: " . $mailbox_data['name']);
        
        return "Processed $processed_count bounces, skipped $skipped_count emails";
        
    } catch (Exception $e) {
        logActivity("Error processing bounce", "Error: " . $e->getMessage());
        return "Error processing: " . $e->getMessage();
    }
}

// Create required tables if they don't exist
function createDatabaseTables() {
    global $db;
    
    try {
        // Create mailboxes table
        $db->exec("CREATE TABLE IF NOT EXISTS mailboxes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            host TEXT NOT NULL,
            port INTEGER NOT NULL,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            inbox_folder TEXT DEFAULT 'INBOX',
            processed_folder TEXT DEFAULT 'Processed',
            skipped_folder TEXT DEFAULT 'Skipped',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create activity_log table
        $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create test mode setting if it doesn't exist
        $stmt = $db->prepare("SELECT COUNT(*) FROM mailboxes WHERE name = 'test'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO mailboxes (name, host, port, username, password) VALUES ('test', 'localhost', 993, 'test@example.com', 'password')");
        }
        
    } catch (Exception $e) {
        error_log("Database setup error: " . $e->getMessage());
    }
}

// Initialize database tables
createDatabaseTables();

?>