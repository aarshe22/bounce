<?php
// Bounce processing logic

require_once 'config.php';
require_once 'imap.php';

function processBounces($db, $mailbox_id) {
    $mailbox = getMailbox($db, $mailbox_id);
    if (!$mailbox) {
        logActivity($db, 'ERROR', "Mailbox not found: $mailbox_id");
        return false;
    }
    
    // Connect to IMAP
    $imap_connection = connectImap($mailbox['host'], $mailbox['port'], $mailbox['username'], $mailbox['password']);
    if (!$imap_connection) {
        logActivity($db, 'ERROR', "Failed to connect to mailbox: {$mailbox['name']}");
        return false;
    }
    
    // Get unread messages
    $messages = getUnreadMessages($imap_connection, $mailbox['inbox_folder']);
    
    foreach ($messages as $message) {
        try {
            logActivity($db, 'INFO', "Processing message: {$message['subject']}", $mailbox_id);
            
            // Fetch raw message
            $raw_message = getRawMessage($imap_connection, $message['id']);
            
            // Check if it's an auto-reply or out-of-office message
            if (isAutoReply($raw_message)) {
                moveMessage($imap_connection, $message['id'], $mailbox['skipped_folder']);
                logActivity($db, 'SKIPPED', "Auto-reply message skipped: {$message['subject']}", $mailbox_id);
                continue;
            }
            
            // Parse bounce message
            $bounce_info = parseBounceMessage($raw_message);
            
            if (!$bounce_info['is_bounce']) {
                moveMessage($imap_connection, $message['id'], $mailbox['skipped_folder']);
                logActivity($db, 'SKIPPED', "Non-bounce message skipped: {$message['subject']}", $mailbox_id);
                continue;
            }
            
            // Get original CC recipients
            $cc_recipients = extractCcRecipients($raw_message);
            $to_address = $bounce_info['original_to'];
            
            // Send bounce notification (in test mode, send to test recipients)
            $test_settings = getTestSettings($db);
            $send_to = TEST_MODE ? TEST_RECIPIENTS : $cc_recipients;
            
            if (!empty($send_to)) {
                sendBounceNotification($to_address, $bounce_info['smtp_code'], $send_to, $test_settings);
                logActivity($db, 'NOTIFICATION', "Bounce notification sent for: {$to_address}", $mailbox_id);
            }
            
            // Move message to processed folder
            moveMessage($imap_connection, $message['id'], $mailbox['processed_folder']);
            logActivity($db, 'PROCESSED', "Bounce message processed: {$message['subject']}", $mailbox_id);
            
        } catch (Exception $e) {
            logActivity($db, 'ERROR', "Error processing message: {$e->getMessage()}", $mailbox_id);
        }
    }
    
    // Close connection
    imap_close($imap_connection);
    
    return true;
}

function extractCcRecipients($raw_message) {
    $recipients = [];
    
    // Extract CC addresses from headers
    if (preg_match('/CC:\s*(.*?)(?:\r\n|\n)/i', $raw_message, $matches)) {
        $cc_header = trim($matches[1]);
        $addresses = explode(',', $cc_header);
        foreach ($addresses as $address) {
            $recipients[] = trim($address);
        }
    }
    
    return $recipients;
}

function sendBounceNotification($original_to, $smtp_code, $recipients, $test_settings) {
    // In a real implementation, this would send an email
    // For now, we'll just log the action
    
    $subject = "Delivery Failure Notification";
    $body = "Message delivery failed for: $original_to\n";
    $body .= "SMTP Code: $smtp_code\n";
    $body .= "Recipients notified: " . implode(', ', $recipients) . "\n";
    
    if (TEST_MODE && $test_settings['enabled']) {
        $body .= "TEST MODE: Message sent to test recipients only.\n";
    }
    
    // Log notification
    error_log("Bounce Notification:\n$body");
}

?>