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
                
                $stmt = $db->prepare("UPDATE mailboxes SET name=?, host=?, port=?, username=?, password=?, inbox_folder=?, processed_folder=?, skipped_folder=? WHERE id=?");
                $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder, $id]);
                logActivity("Edited mailbox", "Edited mailbox ID: $id");
                break;
                
            case 'delete_mailbox':
                $id = $_POST['id'];
                $stmt = $db->prepare("DELETE FROM mailboxes WHERE id=?");
                $stmt->execute([$id]);
                logActivity("Deleted mailbox", "Deleted mailbox ID: $id");
                break;
                
            case 'process_bounces':
                $mailbox_id = $_POST['mailbox_id'];
                $result = processBounce($mailbox_id);
                logActivity("Manual processing", "Processed mailbox ID: $mailbox_id - Result: $result");
                break;
        }
    }
}

// Get all mailboxes
$mailboxes = $db->query("SELECT * FROM mailboxes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get test settings
$test_settings = $db->query("SELECT * FROM test_settings")->fetch(PDO::FETCH_ASSOC);
if (!$test_settings) {
    $db->exec("INSERT INTO test_settings (enabled) VALUES (0)");
    $test_settings = ['enabled' => 0];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bounce Processor Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007cba;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #005a87;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            color: black;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .edit-form {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bounce Processor Admin</h1>
        
        <!-- Test Mode Settings -->
        <h2>Test Mode Settings</h2>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="toggle_test_mode">
            <label>
                <input type="checkbox" name="test_enabled" <?php echo $test_settings['enabled'] ? 'checked' : ''; ?>> 
                Enable Test Mode
            </label>
            <button type="submit">Save Settings</button>
        </form>
        
        <!-- Add New Mailbox -->
        <h2>Add New Mailbox</h2>
        <div class="edit-form">
            <form method="POST">
                <input type="hidden" name="action" value="add_mailbox">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="host">Host:</label>
                    <input type="text" id="host" name="host" required>
                </div>
                <div class="form-group">
                    <label for="port">Port:</label>
                    <input type="text" id="port" name="port" value="993" required>
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
                <button type="submit" class="btn-success">Add Mailbox</button>
            </form>
        </div>
        
        <!-- Mailboxes List -->
        <h2>Mailboxes</h2>
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
                            <button onclick="editMailbox(<?php echo $mailbox['id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                <input type="hidden" name="action" value="delete_mailbox">
                                <input type="hidden" name="id" value="<?php echo $mailbox['id']; ?>">
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="process_bounces">
                                <input type="hidden" name="mailbox_id" value="<?php echo $mailbox['id']; ?>">
                                <button type="submit" class="btn-warning">Process</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Edit Mailbox Form (Hidden by default) -->
        <div id="edit-form-container" class="edit-form hidden">
            <h3>Edit Mailbox</h3>
            <form method="POST" id="edit-form">
                <input type="hidden" name="action" value="edit_mailbox">
                <input type="hidden" id="edit-id" name="id">
                <div class="form-group">
                    <label for="edit-name">Name:</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit-host">Host:</label>
                    <input type="text" id="edit-host" name="host" required>
                </div>
                <div class="form-group">
                    <label for="edit-port">Port:</label>
                    <input type="text" id="edit-port" name="port" value="993" required>
                </div>
                <div class="form-group">
                    <label for="edit-username">Username:</label>
                    <input type="text" id="edit-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit-password">Password:</label>
                    <input type="password" id="edit-password" name="password">
                    <small>Leave blank to keep current password</small>
                </div>
                <div class="form-group">
                    <label for="edit-inbox_folder">Inbox Folder:</label>
                    <input type="text" id="edit-inbox_folder" name="inbox_folder" value="INBOX">
                </div>
                <div class="form-group">
                    <label for="edit-processed_folder">Processed Folder:</label>
                    <input type="text" id="edit-processed_folder" name="processed_folder" value="Processed">
                </div>
                <div class="form-group">
                    <label for="edit-skipped_folder">Skipped Folder:</label>
                    <input type="text" id="edit-skipped_folder" name="skipped_folder" value="Skipped">
                </div>
                <button type="submit" class="btn-success">Update Mailbox</button>
                <button type="button" onclick="cancelEdit()" class="btn-danger">Cancel</button>
            </form>
        </div>
        
        <!-- Manual Processing -->
        <h2>Manual Processing</h2>
        <form method="POST">
            <input type="hidden" name="action" value="process_bounces">
            <div class="form-group">
                <label for="manual-mailbox">Select Mailbox:</label>
                <select id="manual-mailbox" name="mailbox_id" required>
                    <option value="">Choose a mailbox</option>
                    <?php foreach ($mailboxes as $mailbox): ?>
                        <option value="<?php echo $mailbox['id']; ?>"><?php echo htmlspecialchars($mailbox['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-warning">Process Selected Mailbox</button>
        </form>
    </div>

    <script>
        function editMailbox(id) {
            // Get the row data
            const row = event.target.closest('tr');
            const name = row.cells[0].textContent;
            const host = row.cells[1].textContent;
            const port = row.cells[2].textContent;
            const username = row.cells[3].textContent;
            const inbox_folder = row.cells[4].textContent;
            const processed_folder = row.cells[5].textContent;
            const skipped_folder = row.cells[6].textContent;
            
            // Fill the edit form
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-host').value = host;
            document.getElementById('edit-port').value = port;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-inbox_folder').value = inbox_folder;
            document.getElementById('edit-processed_folder').value = processed_folder;
            document.getElementById('edit-skipped_folder').value = skipped_folder;
            
            // Show the edit form
            document.getElementById('edit-form-container').classList.remove('hidden');
            document.getElementById('edit-form-container').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            document.getElementById('edit-form-container').classList.add('hidden');
        }
    </script>
</body>
</html>