<?php
// IMAP handling functions for bounce processing

function connectImap($host, $port, $username, $password) {
    $connection_string = sprintf('{%s:%d/imap/ssl}', $host, $port);
    return imap_open($connection_string, $username, $password);
}

function getUnreadMessages($imap_connection, $folder = 'INBOX') {
    if (!imap_reopen($imap_connection, sprintf('{%s:%d/imap/ssl}%s', 
        parse_url($imap_connection, PHP_URL_HOST), 
        parse_url($imap_connection, PHP_URL_PORT),
        $folder))) {
        return [];
    }
    
    $messages = [];
    $message_count = imap_num_msg($imap_connection);
    
    for ($i = 1; $i <= $message_count; $i++) {
        $header = imap_headerinfo($imap_connection, $i);
        
        // Check if message is unread
        if (isset($header->Unseen) && $header->Unseen == 'U') {
            $messages[] = [
                'id' => $i,
                'subject' => $header->Subject,
                'from' => $header->From,
                'date' => $header->Date
            ];
        }
    }
    
    return $messages;
}

function getMessageBody($imap_connection, $message_id) {
    // Get full message
    $body = imap_fetchbody($imap_connection, $message_id, 1.2);
    if (empty($body)) {
        $body = imap_fetchbody($imap_connection, $message_id, 1);
    }
    
    return $body;
}

function getRawMessage($imap_connection, $message_id) {
    return imap_fetchheader($imap_connection, $message_id) . "\n" . 
           imap_fetchbody($imap_connection, $message_id, 1.1);
}

function moveMessage($imap_connection, $message_id, $folder) {
    $folder_name = explode('}', $imap_connection)[1];
    return imap_mail_move($imap_connection, $message_id, $folder);
}

function deleteMessage($imap_connection, $message_id) {
    return imap_delete($imap_connection, $message_id);
}

function parseBounceMessage($raw_message) {
    // Simple parsing for bounce detection
    $is_bounce = false;
    $smtp_code = '';
    $recipient = '';
    $original_to = '';
    
    // Extract SMTP code and recipient from message
    if (preg_match('/5\.\d+\.\d+/', $raw_message, $matches)) {
        $smtp_code = $matches[0];
        $is_bounce = true;
    }
    
    // Try to extract original recipient
    if (preg_match('/Original Recipient:\s*(.*?)(?:\r\n|\n)/i', $raw_message, $matches)) {
        $recipient = trim($matches[1]);
    }
    
    // Try to extract original to address from headers
    if (preg_match('/To:\s*(.*?)(?:\r\n|\n)/i', $raw_message, $matches)) {
        $original_to = trim($matches[1]);
    }
    
    return [
        'is_bounce' => $is_bounce,
        'smtp_code' => $smtp_code,
        'recipient' => $recipient,
        'original_to' => $original_to
    ];
}

function isAutoReply($raw_message) {
    // Check for auto-reply indicators
    $auto_reply_indicators = [
        '/out of office/i',
        '/auto.reply/i',
        '/automatic reply/i',
        '/vacation/i',
        '/away from office/i'
    ];
    
    foreach ($auto_reply_indicators as $pattern) {
        if (preg_match($pattern, $raw_message)) {
            return true;
        }
    }
    
    return false;
}

?>