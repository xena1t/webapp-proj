<?php
// Copy this file to config.php and adjust the credentials for your local setup.
//
// By default we ship the project with a lightweight SQLite database so the
// storefront works out of the box without installing MySQL.  If you already
// have a MySQL server provisioned, simply comment out the DB_DSN line below and
// fill in the traditional host/name/user/pass values instead.
define('DB_DSN', 'sqlite:' . __DIR__ . '/data/techmart.sqlite');

define('DB_HOST', 'localhost');
define('DB_NAME', 'techmart');
define('DB_USER', 'root');
define('DB_PASS', '');

define('MAIL_FROM_ADDRESS', 'orders@techmart.local');
define('MAIL_FROM_NAME', 'TechMart Online');
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 1025); // Example: MailHog/MercuryMail default port

define('SITE_NAME', 'TechMart Online');
