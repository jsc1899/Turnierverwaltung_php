<?php
// Router-Script für PHP Built-in-Server.
// Statische Dateien direkt ausliefern, alles andere → index.php

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

// Existierende Dateien (CSS, JS, Bilder, uploads) direkt ausliefern
if ($path !== '/' && is_file($file)) {
    return false;
}

// Alles andere über index.php routen (auch Pfade mit Punkten/Tokens)
require __DIR__ . '/index.php';
