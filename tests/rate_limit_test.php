<?php
// CLI-Test Rate-Limit: php tests/rate_limit_test.php (MariaDB muss laufen)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.77';   // Test-IP (Dokumentationsbereich)
db_execute("DELETE FROM rate_limit WHERE action LIKE 'rltest%'");

echo "rate_limit_check pro IP\n";
$res = [];
for ($i = 0; $i < 5; $i++) $res[] = rate_limit_check('rltest', 3, 60);
check('3 erlaubt, dann gesperrt', $res === [true, true, true, false, false]);
db_execute("UPDATE rate_limit SET window_start = NOW() - INTERVAL 2 MINUTE WHERE action='rltest'");
check('nach Ablauf des Fensters wieder erlaubt', rate_limit_check('rltest', 3, 60) === true);

echo "rate_limit_check pro Ziel (unabhängig von der IP)\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
$a = rate_limit_check('rltest_target', 2, 3600, 'Opfer@Beispiel.at');
$_SERVER['REMOTE_ADDR'] = '203.0.113.2';
$b = rate_limit_check('rltest_target', 2, 3600, 'opfer@beispiel.at');
$_SERVER['REMOTE_ADDR'] = '203.0.113.3';
$c = rate_limit_check('rltest_target', 2, 3600, 'opfer@beispiel.at');
check('Ziel-Limit greift über IPs hinweg (Groß/Klein egal)', [$a, $b, $c] === [true, true, false]);
check('anderes Ziel unabhängig', rate_limit_check('rltest_target', 2, 3600, 'jemand@beispiel.at') === true);

db_execute("DELETE FROM rate_limit WHERE action LIKE 'rltest%'");
echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
