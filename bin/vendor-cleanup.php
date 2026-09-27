<?php
// Entfernt Hilfs-/Beispielskripte aus vendor/, die im Betrieb nicht benötigt werden, unter nginx
// aber direkt per URL ausführbar wären. Läuft automatisch nach „composer install/update“
// (composer.json → scripts.post-autoload-dump); manuell: php bin/vendor-cleanup.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen

$files = [
    'vendor/phpmailer/phpmailer/get_oauth_token.php',
    'vendor/paragonie/random_compat/other/build_phar.php',
    'vendor/paragonie/random_compat/psalm-autoload.php',
];
$root = dirname(__DIR__);
foreach ($files as $f) {
    $p = $root . '/' . $f;
    if (is_file($p) && unlink($p)) echo "Entfernt (nicht benötigt, direkt aufrufbar): $f" . PHP_EOL;
}
