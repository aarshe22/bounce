<?php
// Main application interface
require_once 'db.php';
require_once 'bounce.php';

// Check if we're running from CLI or web
$is_cli = (php_sapi_name() === 'cli');

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
    // More detailed error for debugging
    if ($is_cli) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        echo "<div class='notification error'>Database connection failed: " . $e->getMessage() . "</div>";
        echo "<div class='notification error'>Please check that the web server has write permissions to the app.db file.</div>";
        // Don't die in web mode, just show error
    }
}

// Only define constants if they don't already exist
if (!defined('TEST_MODE')) {
    define('TEST_MODE', false);
}
if (!defined('TEST_RECIPIENTS')) {
    define('TEST_RECIPIENTS', []);
}

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
        <?php if (!$is_cli): ?>
            <h1>Bounce Processor Admin</h1>
        <?php endif; ?>

        <?php
        // Check if we can write to the database
        try {
            $stmt = $db->query("SELECT 1");
        } catch (PDOException $e) {
            echo "<div class='notification error'>Database access error: " . $e->getMessage() . "</div>";
            echo "<div class='notification error'>Please ensure the web server has write permissions to /var/www/html/imap-bounce/app.db</div>";
        }
        ?>

        <!-- Rest of your existing HTML content -->
        
        <h2>Test Mode Settings</h2>
        <form method="POST">
            <input type="hidden" name="action" value="test_settings">
            <div class="form-group">
                <label><input type="checkbox" name="enabled" value="1" <?php echo TEST_MODE ? 'checked' : ''; ?>> Enable Test Mode</label>
            </div>
            <div class="form-group">
                <label for="recipients">Test Recipients (comma separated):</label>
                <input type="text" id="recipients" name="recipients" value="<?php echo htmlspecialchars(implode(',', TEST_RECIPIENTS)); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>

        <!-- Rest of your existing content -->
    </div>
</body>
</html>
