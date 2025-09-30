\<?php
// config.php - Configuration file
return [
    'db_path' => __DIR__ . '/database.sqlite',
    'default_imap_host' => 'localhost',
    'default_imap_port' => 993,
    'notification_from_email' => 'bounces@localhost',
    'notification_from_name' => 'Bounce Handler',
    // Optional SMTP relay for delivering notifications. Leave 'host' empty to use PHP mail().
    'smtp' => [
        'host' => '',        // e.g. 'smtp.example.com' (empty to disable)
        'port' => 587,       // 25, 465 (ssl), or 587 (tls)
        'username' => '',    // SMTP username
        'password' => '',    // SMTP password
        'security' => 'tls', // 'none' | 'tls' | 'ssl'
        // Optional: override From for SMTP specifically (falls back to notification_from_* above)
        'from_email' => '',
        'from_name' => ''
    ],
    'smtp_error_codes' => [
        '550' => 'Mailbox unavailable or user does not exist',
        '552' => 'Mailbox full or message too large',
        '553' => 'Mailbox name not allowed',
        '554' => 'Transaction failed',
        '421' => 'Service not available, closing transmission channel',
        '450' => 'Requested mail action not taken: mailbox unavailable',
        '451' => 'Requested action aborted: local error in processing',
        '452' => 'Requested action not taken: insufficient system storage'
    ],
    'bounce_patterns' => [
        '/^.*undeliverable.*$/i',
        '/^.*delivery failed.*$/i',
        '/^.*mail delivery failed.*$/i',
        '/^.*failed to deliver.*$/i',
        '/^.*message not delivered.*$/i',
        '/^.*returned mail.*$/i'
    ]
];
?>