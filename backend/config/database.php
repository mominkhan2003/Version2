<?php
/**
 * LOCAL TEST configuration (gitignored) — MariaDB on localhost:3307.
 * Production copy goes outside public_html on the cPanel host.
 */
declare(strict_types=1);

return [
    'host'        => 'premium281.web-hosting.com',
    'port'        => '3307',
    'database'    => 'prosdfwo_mcg',
    'username'    => 'prosdfwo_mcg',
    'password'    => 'MensaGraz@2026',
    'charset'     => 'utf8mb4',

    'storage_dir' => 'H:/tmp/opencode/mcg_storage',

    'base_url'    => 'http://localhost:8080',

    'mail_from'   => 'no-reply@localhost.test',

    'dev_origins' => [
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:8787',
        'http://127.0.0.1:8787',
    ],
];
