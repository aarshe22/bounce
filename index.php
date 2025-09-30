<?php
// Main application interface
require_once 'db.php';
require_once 'bounce.php';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_mailbox':
                $name = $_POST['name'];
                $host = $_POST['host'];
                $port = $_POST['port'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $inbox_folder = $_POST['inbox_folder'] ?? 'INBOX';
                $processed_folder = $_POST['processed_folder'] ?? 'Processed';
                $skipped_folder = $_POST['skipped_folder'] ?? 'Skipped';
                
                $stmt = $db->prepare("INSERT INTO mailboxes (name, host, port, username, password, inbox_folder, processed_folder, skipped_folder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder]);
                logActivity("Added mailbox", "Added mailbox: $name");
                break;
                
            case 'edit_mailbox':
                $id = $_POST['id'];
                $name = $_POST['name'];
                $host = $_POST['host'];
                $port = $_POST['port'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $inbox_folder = $_POST['inbox_folder'] ?? 'INBOX';
                $processed_folder = $_POST['processed_folder'] ?? 'Processed';
                $skipped_folder = $_POST['skipped_folder'] ?? 'Skipped';
                
                // If password is empty, don't update it
                if (empty($password)) {
                    $stmt = $db->prepare("UPDATE mailboxes SET name=?, host=?, port=?, username=?, inbox_folder=?, processed_folder=?, skipped_folder=? WHERE id=?");
                    $stmt->execute([$name, $host, $port, $username, $inbox_folder, $processed_folder, $skipped_folder, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE mailboxes SET name=?, host=?, port=?, username=?, password=?, inbox_folder=?, processed_folder=?, skipped_folder=? WHERE id=?");
                    $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder, $id]);
                }
                logActivity("Edited mailbox", "Edited mailbox ID: $id");
                break;
                
            case 'delete_mailbox':
                $id = $_POST['id'];
                $stmt = $db->prepare("DELETE FROM mailboxes WHERE id=?");
                $stmt->execute([$id]);
                logActivity("Deleted mailbox", "Deleted mailbox ID: $id");
                break;
                
            case 'toggle_test_mode':
                $enabled = isset($_POST['test_enabled']) ? 1 : 0;
                $stmt = $db->prepare("UPDATE test_settings SET enabled=? WHERE id=1");
                $stmt->execute([$enabled]);
                logActivity("Test mode toggle", "Test mode set to: " . ($enabled ? 'ON' : 'OFF'));
                break;
                
            case 'process_bounces':
                $mailbox_id = $_POST['mailbox_id'];
                // Process the mailbox with debug logging
                processMailboxWithDebug($mailbox_id);
                break;
        }
    }
}

// Function to process mailbox with debug logging
function processMailboxWithDebug($mailbox_id) {
    global $db;
    
    // Get mailbox details
    $stmt = $db->prepare("SELECT * FROM mailboxes WHERE id = ?");
    $stmt->execute([$mailbox_id]);
    $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mailbox) {
        logActivity("Processing error", "Mailbox not found: $mailbox_id");
        return;
    }
    
    // Log start of processing
    logActivity("Processing start", "Starting to process mailbox: {$mailbox['name']}");
    
    try {
        // Connect to IMAP
        $imap_connection = imap_open("{{$mailbox['host']}:{$mailbox['port']}/imap/ssl}INBOX", $mailbox['username'], $mailbox['password']);
        
        if (!$imap_connection) {
            logActivity("IMAP connection error", "Failed to connect to mailbox: {$mailbox['name']}");
            return;
        }
        
        logActivity("IMAP connection", "Connected to mailbox: {$mailbox['name']}");
        
        // Get message count
        $message_count = imap_num_msg($imap_connection);
        logActivity("Message count", "Found $message_count messages in mailbox: {$mailbox['name']}");
        
        if ($message_count == 0) {
            logActivity("Processing completed", "No messages to process in mailbox: {$mailbox['name']}");
            imap_close($imap_connection);
            return;
        }
        
        // Process each message
        for ($i = 1; $i <= $message_count; $i++) {
            logActivity("Message processing", "Processing message #$i in mailbox: {$mailbox['name']}");
            
            // Get message header
            $header = imap_headerinfo($imap_connection, $i);
            logActivity("Message header", "Subject: {$header->subject}, From: {$header->from[0]->mailbox}@{$header->from[0]->host}");
            
            // Fetch message body
            $body = imap_fetchbody($imap_connection, $i, FT_PEEK);
            logActivity("Message body fetched", "Message body length: " . strlen($body) . " characters");
            
            // Check if it's a bounce email (simple check)
            if (strpos(strtolower($body), 'bounce') !== false || 
                strpos(strtolower($body), 'undelivered') !== false ||
                strpos(strtolower($body), 'delivery failed') !== false) {
                
                logActivity("Bounce detected", "Bounce message detected in message #$i");
                
                // Process bounce (simplified for demo)
                $bounce_info = processBounce($body);
                logActivity("Bounce processed", "Processed bounce info: " . json_encode($bounce_info));
                
                // Move to processed folder
                if (imap_mail_move($imap_connection, $i, $mailbox['processed_folder'])) {
                    logActivity("Message moved", "Moved message #$i to processed folder");
                } else {
                    logActivity("Move failed", "Failed to move message #$i to processed folder");
                }
            } else {
                logActivity("Non-bounce message", "Message #$i is not a bounce - moving to skipped folder");
                
                // Move to skipped folder
                if (imap_mail_move($imap_connection, $i, $mailbox['skipped_folder'])) {
                    logActivity("Message moved", "Moved message #$i to skipped folder");
                } else {
                    logActivity("Move failed", "Failed to move message #$i to skipped folder");
                }
            }
        }
        
        // Commit moves
        imap_expunge($imap_connection);
        logActivity("Processing completed", "Completed processing mailbox: {$mailbox['name']}");
        
        // Close connection
        imap_close($imap_connection);
        
    } catch (Exception $e) {
        logActivity("Processing error", "Error during processing: " . $e->getMessage());
    }
}

// Simplified bounce processing function
function processBounce($body) {
    // This is a simplified example - in reality, you'd parse the bounce content properly
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'body_length' => strlen($body),
        'has_bounce_keyword' => strpos(strtolower($body), 'bounce') !== false,
        'has_undelivered' => strpos(strtolower($body), 'undelivered') !== false
    ];
}

// Get all mailboxes
$stmt = $db->query("SELECT * FROM mailboxes ORDER BY name");
$mailboxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get test mode setting
$stmt = $db->query("SELECT enabled FROM test_settings WHERE id=1");
$test_mode = $stmt->fetch(PDO::FETCH_ASSOC);
$test_mode_enabled = $test_mode ? $test_mode['enabled'] : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bounce Processor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .edit-form { background-color: #f5f5f5; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .hidden { display: none; }
        .action-buttons { white-space: nowrap; }
        .btn { padding: 5px 10px; margin: 2px; cursor: pointer; }
        .btn-success { background-color: #4CAF50; color: white; }
        .btn-danger { background-color: #f44336; color: white; }
        .btn-warning { background-color: #ff9800; color: white; }
        .form-group { margin-bottom: 10px; }
        label { display: block; margin-top: 10px; }
        input, select { width: 300px; padding: 5px; }
    </style>
</head>
<body>
    <h1>Bounce Processor</h1>
    
    <!-- Test Mode Toggle -->
    <div>
        <h2>Test Mode</h2>
        <form method="POST">
            <input type="hidden" name="action" value="toggle_test_mode">
            <label>
                <input type="checkbox" name="test_enabled" <?php echo $test_mode_enabled ? 'checked' : ''; ?>> 
                Enable Test Mode
            </label>
            <br>
            <button type="submit" class="btn btn-success">Save Settings</button>
        </form>
    </div>
    
    <!-- Add Mailbox -->
    <div>
        <h2>Add New Mailbox</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_mailbox">
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Host:</label>
                <input type="text" name="host" required>
            </div>
            <div class="form-group">
                <label>Port:</label>
                <input type="number" name="port" value="993" required>
            </div>
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Inbox Folder:</label>
                <input type="text" name="inbox_folder" value="INBOX">
            </div>
            <div class="form-group">
                <label>Processed Folder:</label>
                <input type="text" name="processed_folder" value="Processed">
            </div>
            <div class="form-group">
                <label>Skipped Folder:</label>
                <input type="text" name="skipped_folder" value="Skipped">
            </div>
            <button type="submit" class="btn btn-success">Add Mailbox</button>
        </form>
    </div>
    
    <!-- Mailbox List -->
    <div>
        <h2>Mailboxes</h2>
        <?php if (count($mailboxes) > 0): ?>
            <table border="1" style="width: 100%; border-collapse: collapse;">
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
                            <button onclick="editMailbox(<?php echo $mailbox['id']; ?>)" class="btn btn-warning">Edit</button>
                            <button onclick="deleteMailbox(<?php echo $mailbox['id']; ?>)" class="btn btn-danger">Delete</button>
                            <button onclick="processMailbox(<?php echo $mailbox['id']; ?>)" class="btn btn-success">Process</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No mailboxes configured.</p>
        <?php endif; ?>
    </div>
    
    <!-- Edit Form -->
    <div id="editForm" class="edit-form hidden">
        <h2>Edit Mailbox</h2>
        <form method="POST" id="editFormContent">
            <input type="hidden" name="action" value="update_mailbox">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" id="editName" required>
            </div>
            <div class="form-group">
                <label>Host:</label>
                <input type="text" name="host" id="editHost" required>
            </div>
            <div class="form-group">
                <label>Port:</label>
                <input type="number" name="port" id="editPort" value="993" required>
            </div>
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" id="editUsername" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" id="editPassword">
                <small>Leave blank to keep current password</small>
            </div>
            <div class="form-group">
                <label>Inbox Folder:</label>
                <input type="text" name="inbox_folder" id="editInboxFolder">
            </div>
            <div class="form-group">
                <label>Processed Folder:</label>
                <input type="text" name="processed_folder" id="editProcessedFolder">
            </div>
            <div class="form-group">
                <label>Skipped Folder:</label>
                <input type="text" name="skipped_folder" id="editSkippedFolder">
            </div>
            <button type="submit" class="btn btn-success">Update Mailbox</button>
            <button type="button" onclick="cancelEdit()" class="btn btn-danger">Cancel</button>
        </form>
    </div>
    
    <!-- Manual Processing -->
    <div>
        <h2>Manual Processing</h2>
        <form method="POST">
            <input type="hidden" name="action" value="process_bounces">
            <div class="form-group">
                <label>Select Mailbox:</label>
                <select name="mailbox_id" required>
                    <option value="">Choose a mailbox</option>
                    <?php foreach ($mailboxes as $mailbox): ?>
                        <option value="<?php echo $mailbox['id']; ?>">
                            <?php echo htmlspecialchars($mailbox['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Process Selected Mailbox</button>
        </form>
    </div>

    <script>
        function editMailbox(id) {
            // This would normally fetch the mailbox data and populate the form
            document.getElementById('editId').value = id;
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function cancelEdit() {
            document.getElementById('editForm').classList.add('hidden');
        }
        
        function deleteMailbox(id) {
            if (confirm('Are you sure you want to delete this mailbox?')) {
                // In a real implementation, this would make an AJAX call
                alert('Delete functionality would be implemented here');
            }
        }
        
        function processMailbox(id) {
            if (confirm('Are you sure you want to process this mailbox?')) {
                // This would normally make an AJAX call or redirect
                document.querySelector('input[name="mailbox_id"]').value = id;
                document.querySelector('form[action*="process_bounces"]').submit();
            }
        }
    </script>
</body>
</html>