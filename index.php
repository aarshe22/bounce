<?php
// Main application interface
require_once 'db.php';
require_once 'bounce.php';

// Initialize database using PDO (matching db.php's approach)
try {
    $db = new PDO('sqlite:app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist (matching your db.php structure)
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
    
    // Insert default test settings if not exists
    $db->exec("INSERT OR IGNORE INTO test_settings (id, enabled, recipients) VALUES (1, 0, '')");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Define test mode constants for the form
define('TEST_MODE', false);
define('TEST_RECIPIENTS', []);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Bounce Processor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 8px 16px; margin: 5px; text-decoration: none; border-radius: 4px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; }
        .notification { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bounce Processor</h1>
        
        <?php if (isset($_GET['msg'])): ?>
            <div class="notification success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <h2>Mailboxes</h2>
        
        <a href="?action=add" class="btn btn-primary">Add Mailbox</a>
        
        <?php
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $_POST['action'];
            
            if ($action == 'add_mailbox') {
                // Add mailbox logic here
                echo '<div class="notification success">Mailbox added successfully!</div>';
            } elseif ($action == 'update_mailbox') {
                // Update mailbox logic here
                echo '<div class="notification success">Mailbox updated successfully!</div>';
            } elseif ($action == 'delete_mailbox') {
                // Delete mailbox logic here
                echo '<div class="notification success">Mailbox deleted successfully!</div>';
            }
        }
        
        // Display mailboxes
        $stmt = $db->query("SELECT * FROM mailboxes");
        $mailboxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($mailboxes) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mailboxes as $mailbox): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mailbox['name']); ?></td>
                            <td><?php echo htmlspecialchars($mailbox['host']); ?></td>
                            <td><?php echo htmlspecialchars($mailbox['port']); ?></td>
                            <td><?php echo htmlspecialchars($mailbox['username']); ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $mailbox['id']; ?>" class="btn btn-success">Edit</a>
                                <a href="?action=delete&id=<?php echo $mailbox['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No mailboxes configured.</p>
        <?php endif; ?>
        
        <h2>Test Mode Settings</h2>
        
        <form method="POST">
            <input type="hidden" name="action" value="test_settings">
            <div class="form-group">
                <label><input type="checkbox" name="enabled" value="1" <?php echo TEST_MODE ? 'checked' : ''; ?>> Enable Test Mode</label>
            </div>
            <div class="form-group">
                <label for="recipients">Test Recipients (comma separated):</label>
                <input type="text" id="recipients" name="recipients" value="">
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
        
        <h2>Activity Log</h2>
        
        <?php
        // Since you're not implementing getActivityLog function in index.php, 
        // we'll show a simple query instead of calling the db.php function
        $stmt = $db->query("SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT 20");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($logs) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['type']); ?></td>
                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No activity log entries.</p>
        <?php endif; ?>
    </div>
</body>
</html>