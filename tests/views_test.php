<?php
// CLI-Test Zugriffszähler: php tests/views_test.php (MariaDB muss laufen)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php'; require 'auth.php';
require 'lib/views.php';
init_db();

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

echo "view_is_countable\n";
$ua = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130';
check('Gast zählt',            view_is_countable(null, $ua) === true);
check('Viewer zählt',          view_is_countable(['role' => 'viewer'], $ua) === true);
check('Editor zählt nicht',    view_is_countable(['role' => 'editor'], $ua) === false);
check('Admin zählt nicht',     view_is_countable(['role' => 'admin'], $ua) === false);
check('Googlebot zählt nicht', view_is_countable(null, 'Mozilla/5.0 (compatible; Googlebot/2.1)') === false);
check('leerer User-Agent nicht', view_is_countable(null, '') === false);

echo "view_visitor_hash\n";
$h1 = view_visitor_hash('203.0.113.5', $ua, '2026-09-27');
check('stabil am selben Tag',   $h1 === view_visitor_hash('203.0.113.5', $ua, '2026-09-27'));
check('anders am nächsten Tag', $h1 !== view_visitor_hash('203.0.113.5', $ua, '2026-09-28'));
check('andere IP → anders',     $h1 !== view_visitor_hash('203.0.113.6', $ua, '2026-09-27'));
check('keine IP im Hash',       !str_contains($h1, '203.0.113.5') && strlen($h1) === 64);

echo "view_record / view_counts\n";
$db = get_db(); $db->beginTransaction();
$oid = 987654321;
view_record_for('tournament', $oid, 'a', date('Y-m-d'));
view_record_for('tournament', $oid, 'a', date('Y-m-d'));             // gleicher Besucher, gleicher Tag
view_record_for('tournament', $oid, 'b', date('Y-m-d'));
view_record_for('tournament', $oid, 'a', date('Y-m-d', strtotime('-3 days')));
view_record_for('tournament', $oid, 'c', date('Y-m-d', strtotime('-20 days')));
view_record_for('competition', $oid, 'a', date('Y-m-d'));            // anderer Typ
$c = view_counts('tournament', [$oid, 1]);
check('gesamt 4 (Besucher-Tage)', ($c[$oid]['total'] ?? null) === 4);
check('7 Tage: 3',                ($c[$oid]['week'] ?? null) === 3);
check('ohne Daten → 0/0',         ($c[1] ?? null) === ['total' => 0, 'week' => 0]);
view_delete('tournament', [$oid]);
check('löschen entfernt Zähler',  view_counts('tournament', [$oid])[$oid]['total'] === 0);
check('anderer Typ unberührt',    view_counts('competition', [$oid])[$oid]['total'] === 1);
$db->rollBack();

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
