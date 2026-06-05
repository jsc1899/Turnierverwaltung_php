<?php
// .env laden (falls vorhanden) — Werte werden nur gesetzt wenn ENV-Variable noch nicht existiert
$_env_file = __DIR__ . '/.env';
if (is_file($_env_file)) {
    foreach (file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (!str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k);
        $_v = trim($_v);
        if ($_k !== '' && getenv($_k) === false) {
            putenv("$_k=$_v");
        }
    }
    unset($_env_file, $_line, $_k, $_v);
} else {
    unset($_env_file);
}

// Konfiguration — auf dem Server anpassen
$_sk = getenv('SECRET_KEY') ?: 'change-me-in-production';
if ($_sk === 'change-me-in-production' && php_sapi_name() !== 'cli') {
    // Unsicherer Default-Key — in Produktion SECRET_KEY-Umgebungsvariable setzen
    error_log('WARNING: SECRET_KEY is set to the insecure default value.');
}
define('SECRET_KEY', $_sk);
unset($_sk);
define('ADMIN_EMAIL',   getenv('ADMIN_EMAIL')   ?: 'juergen.schlager@gmx.net');

// Datenbank
define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_NAME',   getenv('DB_NAME')   ?: 'turnierverwaltung');
define('DB_USER',   getenv('DB_USER')   ?: 'root');
define('DB_PASS',   getenv('DB_PASS')   ?: '');
define('DB_CHARSET', 'utf8mb4');

// Mail
define('MAIL_HOST',     getenv('MAIL_HOST')     ?: '');
define('MAIL_PORT',     (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM',     getenv('MAIL_FROM')     ?: '');
define('MAIL_TLS',      (bool)(getenv('MAIL_TLS') !== 'false'));

// App
define('APP_URL',   rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'));
define('UPLOAD_DIR', __DIR__ . '/uploads/');
