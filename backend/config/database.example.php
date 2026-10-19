<?php
declare(strict_types=1);

/**
 * SAFE TEMPLATE — copy this file to backend/config/database.php (kept OUTSIDE
 * public_html in production) and fill in the real cPanel values.
 * Never commit the real credentials and never expose them to the frontend.
 */
return [
    // Namecheap cPanel: cPanel → MySQL Databases shows these exact names.
    'host'        => 'premium281.web-hosting.com',
    'port'        => '3307',
    'database'    => 'prosdfwo_mcg',
    'username'    => 'prosdfwo_mcg',
    'password'    => 'MensaGraz@2026',
    'charset'     => 'utf8mb4',

    // Absolute path for uploaded media (outside the webroot, e.g.
    // /home/USER/mcg_storage). Must already exist and be writable by PHP.
    'storage_dir' => '/YOUR/ABSOLUTE/STORAGE/PATH/mcg_storage',

    // The public https:// URL the frontend is served from (used in e-mails).
    'base_url'    => 'https://your-domain.tld',

    // Sender for system e-mails (password reset etc.). cPanel: use a real
    // mailbox of the same domain for best deliverability.
    'mail_from'   => 'no-reply@your-domain.tld',

    // Extra development origins allowed to call the API (same-origin is
    // always allowed). Leave empty in production.
    'dev_origins' => [
        // 'http://localhost:5173',
    ],
];
