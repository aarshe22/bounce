<?php
// index.php - Main web interface
session_start();
require_once 'bounce.php';

// Lightweight JSON endpoint for live activity logs
if (isset($_GET['fetch_activity'])) {
    header('Content-Type: application/json');
    $sinceId = isset($_GET['since']) ? (int)$_GET['since'] : 0;
    try {
        // Fetch logs newer than sinceId
        $dbLogs = $processor->getActivityLogs();
        $result = [];
        foreach ($dbLogs as $row) {
            if (!empty($sinceId) && (int)$row['id'] <= $sinceId) {
                continue;
            }
            $result[] = [
                'id' => (int)$row['id'],
                'timestamp' => $row['timestamp'],
                'action' => $row['action'],
                'details' => $row['details']
            ];
        }
        echo json_encode(['logs' => $result]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Flash helpers
function flash($key) {
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_mailbox':
                $ok = $processor->addMailbox(
                    trim($_POST['name'] ?? ''),
                    trim($_POST['host'] ?? ''),
                    (int)($_POST['port'] ?? 993),
                    trim($_POST['username'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    trim($_POST['inbox_folder'] ?? 'INBOX'),
                    trim($_POST['processed_folder'] ?? 'Processed'),
                    trim($_POST['skipped_folder'] ?? 'Skipped')
                );
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Mailbox added.' : 'Failed to add mailbox.';
                break;
            case 'update_mailbox':
                $ok = $processor->updateMailbox(
                    (int)$_POST['id'],
                    trim($_POST['name'] ?? ''),
                    trim($_POST['host'] ?? ''),
                    (int)($_POST['port'] ?? 993),
                    trim($_POST['username'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    trim($_POST['inbox_folder'] ?? 'INBOX'),
                    trim($_POST['processed_folder'] ?? 'Processed'),
                    trim($_POST['skipped_folder'] ?? 'Skipped')
                );
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Mailbox updated.' : 'Failed to update mailbox.';
                break;
            case 'delete_mailbox':
                $ok = $processor->deleteMailbox((int)$_POST['id']);
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Mailbox deleted.' : 'Failed to delete mailbox.';
                break;
            case 'process_bounces':
                $result = $processor->processBounces((int)$_POST['mailbox_id']);
                $_SESSION[$result['error'] ? 'error' : 'success'] = $result['error'] ?: ("Processed {$result['processed']} bounce emails");
                break;
            case 'update_test_settings':
                $enabled = isset($_POST['test_enabled']) ? 1 : 0;
                $recipients = trim($_POST['test_recipients'] ?? '');
                $ok = $processor->updateTestSettings($enabled, $recipients);
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Test settings saved.' : 'Failed to save test settings.';
                break;
            case 'update_smtp_settings':
                $ok = $processor->updateSmtpSettings([
                    'host' => $_POST['host'] ?? '',
                    'port' => $_POST['port'] ?? 587,
                    'username' => $_POST['username'] ?? '',
                    'password' => $_POST['password'] ?? '',
                    'security' => $_POST['security'] ?? 'tls',
                    'from_email' => $_POST['from_email'] ?? '',
                    'from_name' => $_POST['from_name'] ?? ''
                ]);
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'SMTP settings saved.' : 'Failed to save SMTP settings.';
                break;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Capture flash messages and release session lock early to avoid hangs
$flashSuccess = flash('success');
$flashError = flash('error');
session_write_close();

// Get data for display
$mailboxes = $processor->getMailboxes();
$bounceLogs = $processor->getBounceLogs();
$activityLogs = $processor->getActivityLogs();
$testSettings = $processor->getTestSettings();
$smtpSettings = $processor->getSmtpSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bounce Email Processor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 50px;
        }
        .header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 20px;
        }
        .mailbox-card {
            transition: all 0.3s ease;
        }
        .mailbox-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .badge-bounce {
            background-color: #ff6b6b;
        }
        .badge-processed {
            background-color: #4ecdc4;
        }
        .badge-skipped {
            background-color: #ffd166;
        }
        .nav-link.active {
            color: #2575fc !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-envelope-open-text me-2"></i>Bounce Email Processor</h1>
                    <p class="lead">Automatically detect and process bounce emails from your mailboxes</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" onclick="showAddModal()"><i class="fas fa-plus me-1"></i>Add Mailbox</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><i class="fas fa-inbox"></i> <?php echo count($mailboxes); ?></h3>
                        <p class="text-muted">Mailboxes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><i class="fas fa-envelope"></i> <?php echo count($bounceLogs); ?></h3>
                        <p class="text-muted">Bounce Logs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><i class="fas fa-history"></i> <?php echo count($activityLogs); ?></h3>
                        <p class="text-muted">Activity Logs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Test Mode</h5>
                                <div class="text-muted small">When enabled, notifications go only to override recipients and no messages are moved.</div>
                            </div>
                            <span class="badge <?php echo $testSettings['enabled'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $testSettings['enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                        </div>
                        <form class="mt-3" method="POST">
                            <input type="hidden" name="action" value="update_test_settings">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="test_enabled" name="test_enabled" <?php echo $testSettings['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="test_enabled">Enable Test Mode</label>
                            </div>
                            <div class="mb-2">
                                <label for="test_recipients" class="form-label">Override Recipients (comma-separated)</label>
                                <input type="text" class="form-control" id="test_recipients" name="test_recipients" value="<?php echo htmlspecialchars($testSettings['recipients']); ?>" placeholder="user@example.com, team@example.com">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">SMTP Relay (optional)</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_smtp_settings">
                            <div class="mb-2">
                                <label class="form-label" for="smtp_host">Host</label>
                                <input type="text" class="form-control" id="smtp_host" name="host" value="<?php echo htmlspecialchars($smtpSettings['host'] ?? ''); ?>" placeholder="smtp.example.com">
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label" for="smtp_port">Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="port" value="<?php echo htmlspecialchars($smtpSettings['port'] ?? 587); ?>">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label" for="smtp_security">Security</label>
                                    <select id="smtp_security" class="form-select" name="security">
                                        <?php $sec = strtolower($smtpSettings['security'] ?? 'tls'); ?>
                                        <option value="none" <?php echo $sec==='none' ? 'selected' : ''; ?>>None</option>
                                        <option value="tls" <?php echo $sec==='tls' ? 'selected' : ''; ?>>STARTTLS</option>
                                        <option value="ssl" <?php echo $sec==='ssl' ? 'selected' : ''; ?>>SSL</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="smtp_username">Username</label>
                                <input type="text" class="form-control" id="smtp_username" name="username" value="<?php echo htmlspecialchars($smtpSettings['username'] ?? ''); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="smtp_password">Password</label>
                                <input type="password" class="form-control" id="smtp_password" name="password" value="<?php echo htmlspecialchars($smtpSettings['password'] ?? ''); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="smtp_from_email">From Email (optional)</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="from_email" value="<?php echo htmlspecialchars($smtpSettings['from_email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="smtp_from_name">From Name (optional)</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="from_name" value="<?php echo htmlspecialchars($smtpSettings['from_name'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save SMTP Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Activity Log -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="d-flex align-items-center"><i class="fas fa-terminal me-2"></i>Live Activity</h2>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-muted small">Streams recent actions including IMAP activity and processing steps.</div>
                            <div>
                                <button id="logToggleBtn" class="btn btn-sm btn-outline-primary">Start Live Log</button>
                                <button id="logClearBtn" class="btn btn-sm btn-outline-secondary ms-1">Clear</button>
                            </div>
                        </div>
                        <pre id="liveLog" style="height: 240px; overflow: auto; background:#0b1220; color:#cfe3ff; padding:12px; border-radius:6px; margin:0;">
                        </pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mailboxes Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-server me-2"></i>Mailboxes</h2>
                <?php if (empty($mailboxes)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No mailboxes configured yet. Add your first mailbox to get started.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($mailboxes as $mailbox): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card mailbox-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($mailbox['name']); ?></h5>
                                        <p class="card-text">
                                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($mailbox['username']); ?><br>
                                            <i class="fas fa-server me-2"></i><?php echo htmlspecialchars($mailbox['host']); ?><br>
                                            <i class="fas fa-lock me-2"></i>Port: <?php echo $mailbox['port']; ?>
                                        </p>
                                        <div class="d-flex justify-content-between">
                                            <button class="btn btn-sm btn-outline-primary" onclick="showEditModal(<?php echo (int)$mailbox['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo (int)$mailbox['id']; ?>, '<?php echo htmlspecialchars($mailbox['name']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="process_bounces">
                                                <input type="hidden" name="mailbox_id" value="<?php echo (int)$mailbox['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success w-100">
                                                <i class="fas fa-sync-alt me-1"></i>Process Bounces
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Bounce Logs -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-exclamation-triangle me-2"></i>Bounce Logs</h2>
                <?php if (empty($bounceLogs)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No bounce logs found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mailbox</th>
                                    <th>Email Address</th>
                                    <th>Subject</th>
                                    <th>Error Code</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($bounceLogs, 0, 10) as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['mailbox_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['email_address']); ?></td>
                                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                        <td><span class="badge badge-bounce"><?php echo $log['error_code']; ?></span></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-history me-2"></i>Activity Logs</h2>
                <?php if (empty($activityLogs)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No activity logs found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($activityLogs, 0, 10) as $log): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="mailboxModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="mailboxForm" method="POST">
                    <input type="hidden" name="action" id="actionInput">
                    <input type="hidden" name="id" id="idInput">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Mailbox</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="host" name="host" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="port" class="form-label">Port</label>
                                <input type="number" class="form-control" id="port" name="port" value="993" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="inbox_folder" class="form-label">Inbox Folder</label>
                                <input type="text" class="form-control" id="inbox_folder" name="inbox_folder" value="INBOX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="processed_folder" class="form-label">Processed Folder</label>
                                <input type="text" class="form-control" id="processed_folder" name="processed_folder" value="Processed">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="skipped_folder" class="form-label">Skipped Folder</label>
                                <input type="text" class="form-control" id="skipped_folder" name="skipped_folder" value="Skipped">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Mailbox</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the mailbox "<span id="deleteMailboxName"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDeleteSubmit()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDeleteId = null;
        let liveLogInterval = null;
        let lastLogId = 0;

        function showAddModal() {
            document.getElementById('actionInput').value = 'add_mailbox';
            document.getElementById('modalTitle').textContent = 'Add Mailbox';
            document.getElementById('idInput').value = '';
            document.getElementById('name').value = '';
            document.getElementById('host').value = '';
            document.getElementById('port').value = '993';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            document.getElementById('inbox_folder').value = 'INBOX';
            document.getElementById('processed_folder').value = 'Processed';
            document.getElementById('skipped_folder').value = 'Skipped';
            new bootstrap.Modal(document.getElementById('mailboxModal')).show();
        }

        function showEditModal(id) {
            const mailbox = <?php echo json_encode($mailboxes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>.find(m => parseInt(m.id, 10) === id);
            if (!mailbox) return;
            document.getElementById('actionInput').value = 'update_mailbox';
            document.getElementById('modalTitle').textContent = 'Edit Mailbox';
            document.getElementById('idInput').value = mailbox.id;
            document.getElementById('name').value = mailbox.name;
            document.getElementById('host').value = mailbox.host;
            document.getElementById('port').value = mailbox.port;
            document.getElementById('username').value = mailbox.username;
            document.getElementById('password').value = '';
            document.getElementById('inbox_folder').value = mailbox.inbox_folder;
            document.getElementById('processed_folder').value = mailbox.processed_folder;
            document.getElementById('skipped_folder').value = mailbox.skipped_folder;
            new bootstrap.Modal(document.getElementById('mailboxModal')).show();
        }

        function confirmDelete(id, name) {
            currentDeleteId = id;
            document.getElementById('deleteMailboxName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function confirmDeleteSubmit() {
            if (currentDeleteId !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_mailbox"><input type="hidden" name="id" value="' + currentDeleteId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function appendLogLine(line) {
            const pre = document.getElementById('liveLog');
            pre.textContent += (pre.textContent ? "\n" : "") + line;
            pre.scrollTop = pre.scrollHeight;
        }

        async function fetchLogsOnce() {
            try {
                const res = await fetch('?fetch_activity=1&since=' + lastLogId, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (data && Array.isArray(data.logs)) {
                    for (const row of data.logs) {
                        lastLogId = Math.max(lastLogId, row.id);
                        appendLogLine(`[${row.timestamp}] ${row.action} - ${row.details || ''}`.trim());
                    }
                }
            } catch (e) {
                // swallow
            }
        }

        document.getElementById('logToggleBtn').addEventListener('click', () => {
            if (liveLogInterval) {
                clearInterval(liveLogInterval);
                liveLogInterval = null;
                document.getElementById('logToggleBtn').textContent = 'Start Live Log';
            } else {
                fetchLogsOnce();
                liveLogInterval = setInterval(fetchLogsOnce, 2000);
                document.getElementById('logToggleBtn').textContent = 'Stop Live Log';
            }
        });

        document.getElementById('logClearBtn').addEventListener('click', () => {
            document.getElementById('liveLog').textContent = '';
            lastLogId = 0;
        });
    </script>
</body>
</html>