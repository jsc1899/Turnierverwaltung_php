<?php
// CLI-Test: Endplatzierung im Doppel-KO: php tests/dko_places_test.php (MariaDB muss laufen)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php'; require 'auth.php';
require 'lib/double_ko_bracket.php';
require 'routes/competition.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

$db = get_db();
$db->beginTransaction();   // alle Testdaten werden am Ende verworfen
$tid = (int)db_insert("INSERT INTO tournament (name) VALUES ('DKO-Test')");
$cid = (int)db_insert("INSERT INTO competition (tournament_id, name, mode) VALUES (?, 'DKO', 'double_ko')", [$tid]);
$pids = [];
for ($i = 1; $i <= 8; $i++) {
    $pids[] = (int)db_insert("INSERT INTO player (name, firstname) VALUES (?, '')", ["Spieler$i"]);
}
draw_double_ko($cid, $pids);

// Alle Spiele durchspielen: immer gewinnt player1
for ($guard = 0; $guard < 100; $guard++) {
    $m = db_fetch("SELECT id FROM `match` WHERE competition_id=? AND group_id IS NULL AND played=0
                   AND player1_id IS NOT NULL AND player2_id IS NOT NULL ORDER BY id LIMIT 1", [$cid]);
    if (!$m) break;
    db_execute("UPDATE `match` SET score1=3, score2=1, played=1 WHERE id=?", [$m['id']]);
    recompute_double_ko($cid);
}
_maybe_set_done_dko($cid);
check('Grand Final gespielt', db_fetch("SELECT played FROM `match` WHERE competition_id=? AND bracket='GF'", [$cid])['played'] == 1);

$c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
$places = competition_view_data($c, false, false)['places'];
$ranks = array_map(fn($p) => (int)$p['rank'], $places);
$names = array_map(fn($p) => $p['name'], $places);
echo "  Plätze: " . implode(', ', array_map(fn($p) => $p['rank'] . '.' . $p['name'], $places)) . "\n";
check('alle 8 Spieler platziert',       count($places) === 8);
check('jeder Spieler genau einmal',     count(array_unique($names)) === 8);
check('Ränge 1,2,3,4,5,5,7,7',          $ranks === [1, 2, 3, 4, 5, 5, 7, 7]);

$db->rollBack();
echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
