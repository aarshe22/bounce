<?php
// index.php - Main web interface
require_once 'bounce.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_mailbox':
                $processor->addMailbox(
                    $_POST['name'],
                    $_POST['host'],
                    $_POST['port'],
                    $_POST['username'],
                    $_POST['password'],
                    $_POST['inbox_folder'],
                    $_POST['processed_folder'],
                    $_POST['skipped_folder']
                );
                break;
            case 'update_mailbox':
                $processor->updateMailbox(
                    $_POST['id'],
                    $_POST['name'],
                    $_POST['host'],
                    $_POST['port'],
                    $_POST['username'],
                    $_POST['password'],
                    $_POST['inbox_folder'],
                    $_POST['processed_folder'],
                    $_POST['skipped_folder']
                );
                break;
            case 'delete_mailbox':
                $processor->deleteMailbox($_POST['id']);
                break;
            case 'process_bounces':
                $result = $processor->processBounces($_POST['mailbox_id']);
                if ($result['error']) {
                    $_SESSION['error'] = $result['error'];
                } else {
                    $_SESSION['success'] = "Processed {$result['processed']} bounce emails";
                }
                break;
        }
    }
}

// Get data for display
$mailboxes = $processor->getMailboxes();
$bounceLogs = $processor->getBounceLogs();
$activityLogs = $processor->getActivityLogs();
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
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><i class="fas fa-cogs"></i> 0</h3>
                        <p class="text-muted">Processing Tasks</p>
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
                                            <button class="btn btn-sm btn-outline-primary" onclick="showEditModal(<?php echo $mailbox['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $mailbox['id']; ?>, '<?php echo htmlspecialchars($mailbox['name']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-success w-100" onclick="processBounces(<?php echo $mailbox['id']; ?>)">
                                                <i class="fas fa-sync-alt me-1"></i>Process Bounces
                                            </button>
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
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
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

        function showAddModal() {
            document.getElementById('actionInput').value = 'add';
            document.getElementById('modalTitle').textContent = 'Add Mailbox';
            document.getElementById('idInput').value = '';
            document.getElementById('name').value = '';
            document.getElementById('host').value = '';
            document.getElementById('port').value = '993';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            document.getElementById('inbox_folder').value = 'INBOX';
            new bootstrap.Modal(document.getElementById('mailboxModal')).show();
        }

        function showEditModal(id) {
            // In a real application, you would fetch the mailbox data
            // For now, we'll just show the modal with placeholder values
            document.getElementById('actionInput').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Mailbox';
            document.getElementById('idInput').value = id;
            document.getElementById('name').value = 'Mailbox ' + id;
            document.getElementById('host').value = 'imap.example.com';
            document.getElementById('port').value = '993';
            document.getElementById('username').value = 'user@example.com';
            document.getElementById('password').value = '';
            document.getElementById('inbox_folder').value = 'INBOX';
            new bootstrap.Modal(document.getElementById('mailboxModal')).show();
        }

        function confirmDelete(id, name) {
            currentDeleteId = id;
            document.getElementById('deleteMailboxName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function confirmDeleteSubmit() {
            if (currentDeleteId !== null) {
                // In a real application, you would make an AJAX request to delete
                alert('Mailbox deleted successfully!');
                location.reload(); // Reload to reflect changes
            }
        }

        function processBounces(id) {
            alert('Processing bounces for mailbox ' + id);
            // In a real application, you would make an AJAX request to process bounces
        }
    </script>
</body>
</html>