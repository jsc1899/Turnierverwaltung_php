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

// ── Freilose: 6 Teilnehmer im 8er-Raster (2 Freilose) ──────────────────────────
echo "Doppel-KO mit 6 Teilnehmern (Freilose)
";
$cid6 = (int)db_insert("INSERT INTO competition (tournament_id, name, mode) VALUES (?, 'DKO6', 'double_ko')", [$tid]);
draw_double_ko($cid6, array_slice($pids, 0, 6));
for ($guard = 0; $guard < 100; $guard++) {
    $m = db_fetch("SELECT id FROM `match` WHERE competition_id=? AND group_id IS NULL AND played=0
                   AND player1_id IS NOT NULL AND player2_id IS NOT NULL ORDER BY id LIMIT 1", [$cid6]);
    if (!$m) break;
    db_execute("UPDATE `match` SET score1=3, score2=1, played=1 WHERE id=?", [$m['id']]);
    recompute_double_ko($cid6);
}
$gf6 = db_fetch("SELECT * FROM `match` WHERE competition_id=? AND bracket='GF'", [$cid6]);
check('Grand Final erreicht und gespielt (ohne manuellen Eingriff)', (int)$gf6['played'] === 1);
_maybe_set_done_dko($cid6);
$c6 = db_fetch("SELECT * FROM competition WHERE id=?", [$cid6]);
$pl6 = competition_view_data($c6, false, false)['places'];
echo "  Plätze: " . implode(', ', array_map(fn($p) => $p['rank'] . '.' . $p['name'], $pl6)) . "
";
check('alle 6 Spieler platziert', count($pl6) === 6 && count(array_unique(array_column($pl6, 'name'))) === 6);

// ── Alle Teilnehmerzahlen 3..16: läuft ohne Eingriff durch, jeder genau einmal platziert ──
echo "Doppel-KO 3..16 Teilnehmer
";
$more = $pids;
for ($i = 9; $i <= 16; $i++) $more[] = (int)db_insert("INSERT INTO player (name, firstname) VALUES (?, '')", ["Spieler$i"]);
$bad = [];
foreach (range(3, 16) as $n) {
    $cn = (int)db_insert("INSERT INTO competition (tournament_id, name, mode) VALUES (?, ?, 'double_ko')", [$tid, "DKO$n"]);
    draw_double_ko($cn, array_slice($more, 0, $n));
    for ($guard = 0; $guard < 200; $guard++) {
        $m = db_fetch("SELECT id FROM `match` WHERE competition_id=? AND group_id IS NULL AND played=0
                       AND player1_id IS NOT NULL AND player2_id IS NOT NULL ORDER BY id LIMIT 1", [$cn]);
        if (!$m) break;
        // abwechselnd gewinnt Spieler 1 bzw. 2 → verschiedene Verläufe
        db_execute("UPDATE `match` SET score1=?, score2=?, played=1 WHERE id=?", $m['id'] % 2 ? [3, 1, $m['id']] : [1, 3, $m['id']]);
        recompute_double_ko($cn);
    }
    $cc = db_fetch("SELECT * FROM competition WHERE id=?", [$cn]);
    $pl = competition_view_data($cc, false, false)['places'];
    $gfp = (int)db_fetch("SELECT played FROM `match` WHERE competition_id=? AND bracket='GF'", [$cn])['played'];
    if ($gfp !== 1 || count($pl) !== $n || count(array_unique(array_column($pl, 'name'))) !== $n) $bad[] = $n;
}
check('3..16 Teilnehmer: Grand Final erreicht, alle platziert' . ($bad ? ' (Fehler bei ' . implode(',', $bad) . ')' : ''), $bad === []);

$db->rollBack();
echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
