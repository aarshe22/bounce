<?php
// bounce.php - Core processing logic
require_once 'config.php';
require_once 'imap.php';
$config = require 'config.php';

class BounceProcessor {
    private $db;
    private $config;

    public function __construct($config) {
        $this->config = $config;
        $this->db = new PDO("sqlite:" . $this->config['db_path']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->createDatabaseTables();
    }

    private function createDatabaseTables() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS mailboxes (
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

        $this->db->exec("CREATE TABLE IF NOT EXISTS bounce_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mailbox_id INTEGER,
            email_address TEXT,
            subject TEXT,
            error_code TEXT,
            error_message TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed BOOLEAN DEFAULT 0,
            original_to TEXT,
            cc_addresses TEXT,
            FOREIGN KEY (mailbox_id) REFERENCES mailboxes (id)
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS test_settings (
            id INTEGER PRIMARY KEY,
            enabled INTEGER DEFAULT 0,
            recipients TEXT
        )");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
            id INTEGER PRIMARY KEY,
            host TEXT DEFAULT '',
            port INTEGER DEFAULT 587,
            username TEXT DEFAULT '',
            password TEXT DEFAULT '',
            security TEXT DEFAULT 'tls',
            from_email TEXT DEFAULT '',
            from_name TEXT DEFAULT ''
        )");
        
        // Initialize test settings if not exists
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO test_settings (id, enabled, recipients) VALUES (1, 0, '')");
        $stmt->execute();
        
        // Initialize smtp settings if not exists
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO smtp_settings (id, host, port, username, password, security, from_email, from_name) VALUES (1, '', 587, '', '', 'tls', '', '')");
        $stmt->execute();
    }

    public function logActivity($action, $details = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO activity_logs (action, details) VALUES (?, ?)");
            $stmt->execute([$action, $details]);
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
        }
    }

    public function getMailboxes() {
        $stmt = $this->db->query("SELECT * FROM mailboxes ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addMailbox($name, $host, $port, $username, $password, $inbox_folder = 'INBOX', $processed_folder = 'Processed', $skipped_folder = 'Skipped') {
        try {
            $stmt = $this->db->prepare("INSERT INTO mailboxes (name, host, port, username, password, inbox_folder, processed_folder, skipped_folder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder]);
            $this->logActivity("Added Mailbox", "Name: $name");
            return $result;
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    public function updateMailbox($id, $name, $host, $port, $username, $password, $inbox_folder = 'INBOX', $processed_folder = 'Processed', $skipped_folder = 'Skipped') {
        try {
            // Check if password is provided
            if (!empty($password)) {
                $stmt = $this->db->prepare("UPDATE mailboxes SET name=?, host=?, port=?, username=?, password=?, inbox_folder=?, processed_folder=?, skipped_folder=? WHERE id=?");
                $result = $stmt->execute([$name, $host, $port, $username, $password, $inbox_folder, $processed_folder, $skipped_folder, $id]);
            } else {
                // Keep existing password
                $stmt = $this->db->prepare("UPDATE mailboxes SET name=?, host=?, port=?, username=?, inbox_folder=?, processed_folder=?, skipped_folder=? WHERE id=?");
                $result = $stmt->execute([$name, $host, $port, $username, $inbox_folder, $processed_folder, $skipped_folder, $id]);
            }
            
            $this->logActivity("Updated Mailbox", "ID: $id");
            return $result;
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteMailbox($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM mailboxes WHERE id=?");
            $result = $stmt->execute([$id]);
            $this->logActivity("Deleted Mailbox", "ID: $id");
            return $result;
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    public function getTestSettings() {
        $stmt = $this->db->query("SELECT * FROM test_settings WHERE id=1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateTestSettings($enabled, $recipients) {
        try {
            $stmt = $this->db->prepare("UPDATE test_settings SET enabled=?, recipients=? WHERE id=1");
            $result = $stmt->execute([$enabled, $recipients]);
            return $result;
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    public function getSmtpSettings() {
        $stmt = $this->db->query("SELECT * FROM smtp_settings WHERE id=1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateSmtpSettings($data) {
        try {
            $stmt = $this->db->prepare("UPDATE smtp_settings SET host=?, port=?, username=?, password=?, security=?, from_email=?, from_name=? WHERE id=1");
            $result = $stmt->execute([
                trim($data['host'] ?? ''),
                (int)($data['port'] ?? 587),
                (string)($data['username'] ?? ''),
                (string)($data['password'] ?? ''),
                in_array(strtolower($data['security'] ?? 'tls'), ['none','ssl','tls']) ? strtolower($data['security']) : 'tls',
                trim($data['from_email'] ?? ''),
                trim($data['from_name'] ?? '')
            ]);
            $this->logActivity('Updated SMTP Settings');
            return $result;
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    public function processBounces($mailboxId, $limit = 50) {
        try {
            // Get mailbox details
            $stmt = $this->db->prepare("SELECT * FROM mailboxes WHERE id=?");
            $stmt->execute([$mailboxId]);
            $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mailbox) {
                throw new Exception("Mailbox not found");
            }
            
            // Get test settings
            $testSettings = $this->getTestSettings();
            $isTestMode = (bool)$testSettings['enabled'];
            
            // Connect to IMAP
            $imapPath = "{" . $mailbox['host'] . ":" . $mailbox['port'] . "/imap/ssl}" . $mailbox['inbox_folder'];
            $connection = imap_open($imapPath, $mailbox['username'], $mailbox['password']);
            
            if (!$connection) {
                throw new Exception("IMAP connection failed: " . imap_last_error());
            }
            
            // Search for unread bounce messages
            $search = 'UNSEEN';
            $emails = imap_search($connection, $search);
            
            if (!$emails) {
                $this->logActivity("Processed Bounces", "No emails found in mailbox ID: $mailboxId");
                imap_close($connection);
                return ['processed' => 0, 'error' => 'No emails found'];
            }
            
            // Process messages
            $processed = 0;
            foreach ($emails as $emailNumber) {
                if ($processed >= $limit) {
                    break;
                }
                // Get message headers
                $header = imap_headerinfo($connection, $emailNumber);
                
                // Extract email address from subject or sender
                $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
                $from = isset($header->from[0]) ? ($header->from[0]->mailbox . '@' . $header->from[0]->host) : '';
                
                // Check if it's a bounce message (simplified for demo)
                $isBounce = false;
                foreach ($this->config['bounce_patterns'] as $pattern) {
                    if (preg_match($pattern, $subject)) {
                        $isBounce = true;
                        break;
                    }
                }
                
                if ($isBounce) {
                    // Extract email address from bounce message
                    $emailAddress = $from; // Simplified - in real system would parse bounce content
                    
                    // Get full message for parsing
                    $rawMessage = imap_fetchbody($connection, $emailNumber, 1.1);
                    if (empty($rawMessage)) {
                        $rawMessage = imap_fetchbody($connection, $emailNumber, 1);
                    }
                    
                    // Parse original message headers for To and Cc
                    $originalTo = '';
                    $ccAddresses = [];

                    if (!$isTestMode) {
                        $origHeaders = $this->extractOriginalMessageHeaders($connection, $emailNumber);
                        if (!empty($origHeaders)) {
                            if (preg_match('/^To:\s*(.*)$/im', $origHeaders, $matches)) {
                                $originalTo = trim($matches[1]);
                            }
                            if (preg_match('/^Cc:\s*(.*)$/im', $origHeaders, $ccMatch)) {
                                $ccLine = trim($ccMatch[1]);
                                $ccAddresses = array_map('trim', array_filter(explode(',', $ccLine)));
                            }
                        }
                    }
                    
                    // Log the bounce
                    $stmt = $this->db->prepare("INSERT INTO bounce_logs (mailbox_id, email_address, subject, error_code, error_message, original_to, cc_addresses) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$mailboxId, $emailAddress, $subject, '550', 'Mailbox unavailable', $originalTo, implode(',', $ccAddresses)]);

                    $this->logActivity(
                        'Bounce Detected',
                        sprintf('Mailbox %d | Subject: %s | From: %s', $mailboxId, $subject, $emailAddress)
                    );
                    
                    // In test mode, send to override recipients
                    if ($isTestMode && !empty($testSettings['recipients'])) {
                        $this->sendBounceNotification($testSettings['recipients'], $emailAddress, $subject, $originalTo, $ccAddresses, true);
                    } elseif (!$isTestMode && !empty($ccAddresses)) {
                        $this->sendBounceNotification(implode(',', $ccAddresses), $emailAddress, $subject, $originalTo, $ccAddresses, false);
                    }
                    
                    // Mark as processed and move if not test mode
                    $processed++;
                    if (!$isTestMode) {
                        $targetFolder = !empty($mailbox['processed_folder']) ? $mailbox['processed_folder'] : 'Processed';
                        @imap_mail_move($connection, (string)$emailNumber, $targetFolder);
                    }
                }
            }
            
            if (!$isTestMode) {
                @imap_expunge($connection);
            }
            imap_close($connection);
            
            $this->logActivity("Processed Bounces", "Processed $processed emails in mailbox ID: $mailboxId");
            return ['processed' => $processed, 'error' => null];
        } catch (Exception $e) {
            error_log("Bounce processing error: " . $e->getMessage());
            return ['processed' => 0, 'error' => $e->getMessage()];
        }
    }

    private function extractOriginalMessageHeaders($connection, $msgNo) {
        $structure = imap_fetchstructure($connection, $msgNo);
        $headers = '';
        if (!$structure) {
            return $headers;
        }
        // Traverse parts to find message/rfc822
        $stack = [["struct" => $structure, "prefix" => ""]];
        while (!empty($stack)) {
            $item = array_pop($stack);
            $struct = $item['struct'];
            $prefix = $item['prefix'];
            if (isset($struct->type) && isset($struct->subtype)) {
                // TYPEMESSAGE == 2
                if ((int)$struct->type === 2 && strtoupper($struct->subtype) === 'RFC822') {
                    $partNo = ltrim($prefix, '.');
                    $partNo = $partNo === '' ? '2' : $partNo; // try common part
                    $body = @imap_fetchbody($connection, $msgNo, $partNo);
                    if (!empty($body)) {
                        // Try to split headers from body
                        $segments = preg_split("/\r?\n\r?\n/", $body, 2);
                        if (!empty($segments[0])) {
                            return $segments[0];
                        }
                    }
                }
            }
            if (!empty($struct->parts)) {
                for ($i = 0; $i < count($struct->parts); $i++) {
                    $child = $struct->parts[$i];
                    $childPrefix = $prefix === '' ? (string)($i + 1) : ($prefix . '.' . ($i + 1));
                    $stack[] = ["struct" => $child, "prefix" => $childPrefix];
                }
            }
        }
        // Fallback: try part 2 and part 3 directly
        foreach (['2', '3'] as $p) {
            $body = @imap_fetchbody($connection, $msgNo, $p);
            if (!empty($body)) {
                $segments = preg_split("/\r?\n\r?\n/", $body, 2);
                if (!empty($segments[0])) {
                    return $segments[0];
                }
            }
        }
        // As last resort, include top-level headers (less accurate)
        return @imap_fetchheader($connection, $msgNo) ?: '';
    }

    private function sendBounceNotification($recipients, $emailAddress, $subject, $originalTo, $ccAddresses, $isTest) {
        $to = $recipients;
        $fromName = $this->config['notification_from_name'];
        $fromEmail = $this->config['notification_from_email'];
        $bodyLines = [];
        $bodyLines[] = $isTest ? '[TEST MODE] Bounce Notification' : 'Bounce Notification';
        $bodyLines[] = '';
        $bodyLines[] = 'Bounced email details:';
        $bodyLines[] = 'Subject: ' . $subject;
        $bodyLines[] = 'Reported From: ' . $emailAddress;
        if (!empty($originalTo)) {
            $bodyLines[] = 'Original To: ' . $originalTo;
        }
        if (!empty($ccAddresses)) {
            $bodyLines[] = 'Original Cc: ' . implode(', ', $ccAddresses);
        }
        $body = implode("\r\n", $bodyLines);

        $smtp = $this->getSmtpSettings();
        $smtpHost = $smtp && !empty($smtp['host']) ? $smtp['host'] : '';
        if (!empty($smtpHost)) {
            $smtpFromEmail = !empty($smtp['from_email']) ? $smtp['from_email'] : $fromEmail;
            $smtpFromName = !empty($smtp['from_name']) ? $smtp['from_name'] : $fromName;
            $this->sendViaSmtp($to, ($isTest ? '[TEST] ' : '') . 'Bounce Notification', $body, $smtpFromName, $smtpFromEmail, $smtp);
        } else {
            $headers = [];
            $headers[] = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            @mail($to, ($isTest ? '[TEST] ' : '') . 'Bounce Notification', $body, implode("\r\n", $headers));
        }
    }

    private function sendViaSmtp($to, $subject, $body, $fromName, $fromEmail, $smtp) {
        $host = $smtp['host'] ?? '';
        $port = (int)($smtp['port'] ?? 587);
        $username = $smtp['username'] ?? '';
        $password = $smtp['password'] ?? '';
        $security = strtolower($smtp['security'] ?? 'tls');

        $remote = $host . ':' . $port;
        $transport = ($security === 'ssl') ? 'ssl://' . $host : $host;

        $errno = 0; $errstr = '';
        $fp = @fsockopen($security === 'ssl' ? 'ssl://' . $host : $host, $port, $errno, $errstr, 10);
        if (!$fp) {
            error_log('SMTP connection failed: ' . $errstr);
            return false;
        }

        $read = function() use ($fp) {
            $data = '';
            while ($str = fgets($fp, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            return $data;
        };
        $write = function($cmd) use ($fp) {
            fputs($fp, $cmd . "\r\n");
        };

        $read();
        $write('EHLO localhost');
        $ehlo = $read();

        if ($security === 'tls') {
            $write('STARTTLS');
            $starttls = $read();
            if (strpos($starttls, '220') !== 0) {
                fclose($fp);
                error_log('SMTP STARTTLS failed');
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                error_log('SMTP TLS negotiation failed');
                return false;
            }
            $write('EHLO localhost');
            $ehlo = $read();
        }

        if (!empty($username)) {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($username));
            $read();
            $write(base64_encode($password));
            $authResp = $read();
            if (strpos($authResp, '235') !== 0) {
                fclose($fp);
                error_log('SMTP authentication failed');
                return false;
            }
        }

        $write('MAIL FROM: <' . $fromEmail . '>');
        $read();
        // Support multiple recipients separated by comma
        $recipients = array_map('trim', explode(',', $to));
        foreach ($recipients as $rcpt) {
            if ($rcpt === '') continue;
            $write('RCPT TO: <' . $rcpt . '>');
            $read();
        }
        $write('DATA');
        $read();

        $headers = [];
        $headers[] = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);
        $headers[] = 'To: ' . implode(', ', $recipients);
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        $write($message);
        $read();
        $write('QUIT');
        fclose($fp);
        return true;
    }

    public function getBounceLogs($mailboxId = null) {
        try {
            if ($mailboxId) {
                $stmt = $this->db->prepare("SELECT * FROM bounce_logs WHERE mailbox_id=? ORDER BY timestamp DESC");
                $stmt->execute([$mailboxId]);
            } else {
                $stmt = $this->db->query("SELECT * FROM bounce_logs ORDER BY timestamp DESC");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }

    public function getActivityLogs() {
        try {
            $stmt = $this->db->query("SELECT * FROM activity_logs ORDER BY timestamp DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
}

// Initialize processor
$processor = new BounceProcessor($config);
?>