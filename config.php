<?php
// Configuration file for bounce handler system

// Database configuration
define('DB_FILE', 'database.sqlite');

// Default IMAP settings (can be overridden per mailbox)
define('DEFAULT_IMAP_HOST', '{localhost:993/imap/ssl}');
define('DEFAULT_IMAP_PORT', 993);

// Test mode settings
define('TEST_MODE', false);
define('TEST_RECIPIENTS', ['test@example.com']);

// SMTP error code explanations
$SMTP_ERROR_CODES = [
    '5.1.1' => 'User unknown',
    '5.1.2' => 'Invalid domain name',
    '5.1.3' => 'Bad address syntax',
    '5.1.4' => 'Invalid address',
    '5.1.5' => 'Recipient address rejected',
    '5.1.6' => 'Address incomplete',
    '5.2.0' => 'Message too large',
    '5.3.0' => 'Mailbox unavailable',
    '5.3.1' => 'Mailbox busy',
    '5.3.2' => 'Mailbox full',
    '5.4.0' => 'Address not found',
    '5.4.1' => 'No such user',
    '5.4.2' => 'Invalid address format',
    '5.5.0' => 'Message rejected',
    '5.5.1' => 'Bad destination mailbox',
    '5.5.2' => 'Bad destination system',
    '5.5.3' => 'Bad destination mailbox',
    '5.5.4' => 'Mailbox unavailable',
    '5.5.5' => 'Message too large',
    '5.5.6' => 'Message not accepted',
    '5.5.7' => 'Message content rejected'
];

// Default timezone
date_default_timezone_set('UTC');

?>