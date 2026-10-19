<?php
declare(strict_types=1);

/**
 * Safe template for the private cPanel configuration.
 * Copy this to $HOME/mcg-private/database.php and replace every placeholder.
 * Keep the completed file outside public_html and never commit it.
 */
return [
    'host'        => 'localhost',
    'port'        => '3306',
    'database'    => 'YOUR_CPANEL_DATABASE_NAME',
    'username'    => 'YOUR_CPANEL_DATABASE_USER',
    'password'    => 'YOUR_DATABASE_PASSWORD',
    'charset'     => 'utf8mb4',

    'storage_dir' => '/home/CPANEL_USER/mcg_storage',
    'base_url'    => 'https://your-domain.tld',
    'mail_from'   => 'no-reply@your-domain.tld',

    'dev_origins' => [],
];
