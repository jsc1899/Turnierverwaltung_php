<?php
// .env-Dateien in dieser Reihenfolge: zuerst eine Ebene ÜBER dem App-Ordner (außerhalb des
// Webroots, nicht per URL abrufbar — empfohlen), dann im App-Ordner. Ein Wert wird nur gesetzt,
// wenn er noch nicht existiert: echte ENV-Variablen > äußere .env > innere .env.
function _env_files(string $app_dir): array {
    $files = [];
    foreach ([dirname($app_dir) . '/.env', $app_dir . '/.env'] as $f) {
        if (@is_file($f) && @is_readable($f)) $files[] = $f;
    }
    return $files;
}

function _env_load_file(string $file): void {
    foreach (@file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if ($k !== '' && getenv($k) === false) putenv($k . '=' . trim($v));
    }
}

// Pfad aus der Konfiguration: relative Angaben (z.B. "../turnier_gallery") gelten ab dem App-Ordner
function _env_path(string $value, string $app_dir): string {
    if ($value === '' || $value[0] === '/' || $value[0] === '\\' || preg_match('#^[A-Za-z]:[\\\\/]#', $value)) {
        return $value;
    }
    return $app_dir . '/' . $value;
}

foreach (_env_files(__DIR__) as $_env_file) _env_load_file($_env_file);
unset($_env_file);

// Konfiguration — auf dem Server anpassen
$_sk = getenv('SECRET_KEY') ?: 'change-me-in-production';
if ($_sk === 'change-me-in-production') {
    $_app_url = getenv('APP_URL') ?: '';
    if ($_app_url && strpos($_app_url, 'localhost') === false && php_sapi_name() !== 'cli') {
        die('Konfigurationsfehler: SECRET_KEY muss als Umgebungsvariable gesetzt werden.');
    }
    error_log('WARNING: SECRET_KEY is set to the insecure default value.');
    unset($_app_url);
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

// Galerie (Fotos/Videos je Turnier) — Größen in MB, per ENV überschreibbar
define('GALLERY_MAX_IMAGE_MB', (int)(getenv('GALLERY_MAX_IMAGE_MB') ?: 25));
define('GALLERY_MAX_VIDEO_MB', (int)(getenv('GALLERY_MAX_VIDEO_MB') ?: 500));
define('GALLERY_CHUNK_BYTES',  1024 * 1024);   // 1 MB je Upload-Teil (unter üblichem upload_max_filesize)
define('GALLERY_MAX_EDGE',     2560);          // lange Kante gespeicherter Fotos (px)
define('GALLERY_THUMB_EDGE',   400);           // lange Kante der Vorschaubilder (px)
define('GALLERY_MAX_TOURNAMENT_MB', (int)(getenv('GALLERY_MAX_TOURNAMENT_MB') ?: 5000)); // Speicherlimit je Turnier
define('GALLERY_MAX_MEGAPIXELS', 50);          // größere Fotos werden abgelehnt (GD-Speicherbedarf)
// Speicherort der Galerie-Dateien. Liegt er im Webroot (Default), muss der Webserver den
// Direktzugriff sperren (Apache: .htaccess wird angelegt; nginx: eigene location-Regel nötig).
// Relativ (z.B. GALLERY_DIR=../turnier_gallery) = ab dem App-Ordner, also außerhalb des Webroots.
define('GALLERY_DIR', rtrim(getenv('GALLERY_DIR') ? _env_path(getenv('GALLERY_DIR'), __DIR__) : UPLOAD_DIR . 'gallery', '/\\') . '/');
