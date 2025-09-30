\<?php
// config.php - Configuration file
return [
    'db_path' => __DIR__ . '/bounce_processor.db',
    'default_imap_host' => 'localhost',
    'default_imap_port' => 993,
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