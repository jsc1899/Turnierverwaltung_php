<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "1. autoload laden...<br>";
require_once __DIR__ . '/vendor/autoload.php';
echo "2. OK<br>";

echo "3. config laden...<br>";
require_once __DIR__ . '/config.php';
echo "4. OK - DB_HOST=" . DB_HOST . " DB_NAME=" . DB_NAME . "<br>";

echo "5. DB verbinden...<br>";
$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo "6. DB OK<br>";

echo "7. db.php laden...<br>";
require_once __DIR__ . '/db.php';
echo "8. OK<br>";

echo "9. helpers.php laden...<br>";
require_once __DIR__ . '/helpers.php';
echo "10. OK<br>";

echo "Alle Basis-Komponenten OK!";
