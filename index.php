<?php
// Main application interface
require_once 'db.php';
require_once 'bounce.php';

// Check if we're running from CLI or web
$is_cli = (php_sapi_name() === 'cli');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle test settings
        if (isset($_POST['action']) && $_POST['action'] === 'test_settings') {
            $enabled = isset($_POST['enabled']) ? 1 : 0;
            $recipients = !empty($_POST['recipients']) ? $_POST['recipients'] : '';
            
            $stmt = $db->prepare("UPDATE test_settings SET enabled = ?, recipients = ? WHERE id = 1");
            $stmt->execute([$enabled, $recipients]);
            
            $message = "Test settings saved successfully!";
        }
        
        // Handle mailbox creation
        if (isset($_POST['action']) && $_POST['action'] === 'add_mailbox') {
            $name = $_POST['name'];
            $host = $_POST['host'];
            $port = intval($_POST['port']);
            $username = $_POST['username'];
            $password = $_POST['password'];
            $inbox_folder = $_POST['inbox_folder'] ?? 'INBOX';
            $processed_folder = $_POST['processed_folder'] ?? 'Processed';
            $skipped_folder = $_POST['skipped_folder'] ?? 'Skipped';
            
            $stmt = $db->prepare("INSERT INTO mailboxes (name, host, port, username, password, inbox_folder, processed_folder, skipped_folder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder]);
            
            $message = "Mailbox added successfully!";
        }
        
        // Handle mailbox deletion
        if (isset($_POST['action']) && $_POST['action'] === 'delete_mailbox') {
            $id = intval($_POST['id']);
            $stmt = $db->prepare("DELETE FROM mailboxes WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Mailbox deleted successfully!";
        }
        
        // Handle manual bounce processing
        if (isset($_POST['action']) && $_POST['action'] === 'process_bounces') {
            $mailbox_id = intval($_POST['mailbox_id']);
            $result = processBounce($mailbox_id);
            
            $message = "Bounce processing completed: " . $result;
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all mailboxes
$mailboxes = $db->query("SELECT * FROM mailboxes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get test settings
$test_settings = $db->query("SELECT * FROM test_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (!$test_settings) {
    $db->exec("INSERT OR IGNORE INTO test_settings (id, enabled, recipients) VALUES (1, 0, '')");
    $test_settings = $db->query("SELECT * FROM test_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}

// Get activity log
$activity_log = $db->query("SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Bounce Processor Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .btn { padding: 8px 16px; margin: 5px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; border: none; }
        .btn-success { background-color: #28a745; color: white; border: none; }
        .btn-danger { background-color: #dc3545; color: white; border: none; }
        .btn-warning { background-color: #ffc107; color: black; border: none; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .notification { padding: 12px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .section { margin-bottom: 30px; padding: 20px; border: 1px solid #eee; border-radius: 6px; }
        .action-buttons { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Bounce Processor Admin</h1>
        
        <?php if (isset($message)): ?>
            <div class="notification success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="notification error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Test Mode Settings -->
        <div class="section">
            <h2>⚙️ Test Mode Settings</h2>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_settings">
                <div class="form-group">
                    <label><input type="checkbox" name="enabled" value="1" <?php echo $test_settings['enabled'] ? 'checked' : ''; ?>> Enable Test Mode</label>
                </div>
                <div class="form-group">
                    <label for="recipients">Test Recipients (comma separated):</label>
                    <input type="text" id="recipients" name="recipients" value="<?php echo htmlspecialchars($test_settings['recipients']); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>

        <!-- Add New Mailbox -->
        <div class="section">
            <h2>➕ Add New Mailbox</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_mailbox">
                <div class="form-group">
                    <label for="name">Mailbox Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="host">IMAP Host:</label>
                    <input type="text" id="host" name="host" required placeholder="imap.gmail.com">
                </div>
                <div class="form-group">
                    <label for="port">Port:</label>
                    <input type="number" id="port" name="port" value="993" required>
                </div>
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="inbox_folder">Inbox Folder:</label>
                    <input type="text" id="inbox_folder" name="inbox_folder" value="INBOX">
                </div>
                <div class="form-group">
                    <label for="processed_folder">Processed Folder:</label>
                    <input type="text" id="processed_folder" name="processed_folder" value="Processed">
                </div>
                <div class="form-group">
                    <label for="skipped_folder">Skipped Folder:</label>
                    <input type="text" id="skipped_folder" name="skipped_folder" value="Skipped">
                </div>
                <button type="submit" class="btn btn-success">Add Mailbox</button>
            </form>
        </div>

        <!-- Mailboxes List -->
        <div class="section">
            <h2>📋 Mailboxes</h2>
            <?php if (empty($mailboxes)): ?>
                <p>No mailboxes configured yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Host</th>
                            <th>Port</th>
                            <th>Username</th>
                            <th>Inbox Folder</th>
                            <th>Processed Folder</th>
                            <th>Skipped Folder</th>
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
                            <td><?php echo htmlspecialchars($mailbox['inbox_folder']); ?></td>
                            <td><?php echo htmlspecialchars($mailbox['processed_folder']); ?></td>
                            <td><?php echo htmlspecialchars($mailbox['skipped_folder']); ?></td>
                            <td class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_mailbox">
                                    <input type="hidden" name="id" value="<?php echo $mailbox['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="process_bounces">
                                    <input type="hidden" name="mailbox_id" value="<?php echo $mailbox['id']; ?>">
                                    <button type="submit" class="btn btn-warning">Process Bounces</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Activity Log -->
        <div class="section">
            <h2>📊 Recent Activity</h2>
            <?php if (empty($activity_log)): ?>
                <p>No activity recorded yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_log as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Manual Processing -->
        <div class="section">
            <h2>🔄 Manual Bounce Processing</h2>
            <?php if (!empty($mailboxes)): ?>
                <p>Select a mailbox to process bounces manually:</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="process_bounces">
                    <select name="mailbox_id" required>
                        <option value="">Select a mailbox</option>
                        <?php foreach ($mailboxes as $mailbox): ?>
                            <option value="<?php echo $mailbox['id']; ?>"><?php echo htmlspecialchars($mailbox['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Process Selected Mailbox</button>
                </form>
            <?php else: ?>
                <p>No mailboxes configured. Please add a mailbox first.</p>
            <?php endif; ?>
        </div>

        <!-- System Status -->
        <div class="section">
            <h2>📈 System Status</h2>
            <p><strong>Total Mailboxes:</strong> <?php echo count($mailboxes); ?></p>
            <p><strong>Test Mode:</strong> <?php echo $test_settings['enabled'] ? 'Enabled' : 'Disabled'; ?></p>
        </div>

    </div>
</body>
</html>