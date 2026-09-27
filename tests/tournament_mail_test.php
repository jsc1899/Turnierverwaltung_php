<?php
// CLI-Test: Empfängerliste „Mail an Teilnehmer“: php tests/tournament_mail_test.php (MariaDB muss laufen)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'db.php'; require 'helpers.php'; require 'auth.php';
require 'routes/tournament.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

$db = get_db();
$db->beginTransaction();   // alle Testdaten werden am Ende verworfen
$p = fn(string $name, string $mail) => (int)db_insert("INSERT INTO player (name, email) VALUES (?, ?)", [$name, $mail]);
$tid   = (int)db_insert("INSERT INTO tournament (name) VALUES ('Mail-Test')");
$other = (int)db_insert("INSERT INTO tournament (name) VALUES ('Mail-Andere')");
$cEin  = (int)db_insert("INSERT INTO competition (tournament_id, name) VALUES (?, 'Einzel')", [$tid]);
$cDop  = (int)db_insert("INSERT INTO competition (tournament_id, name, is_doubles) VALUES (?, 'Doppel', 1)", [$tid]);
$cTeam = (int)db_insert("INSERT INTO competition (tournament_id, name, is_team) VALUES (?, 'Team', 1)", [$tid]);
$cOth  = (int)db_insert("INSERT INTO competition (tournament_id, name) VALUES (?, 'Fremd')", [$other]);

$a = $p('Einzel A', 'a@test.at');
$b = $p('Einzel B', '');                    // ohne Mail
$c = $p('Doppel C', 'c@test.at');
$d = $p('Doppel D', 'D@Test.at');
$e = $p('Team E',   'e@test.at');
$f = $p('Team F',   'A@TEST.AT');           // Duplikat von a (Groß/Klein)
$g = $p('Fremd G',  'g@test.at');           // nur im fremden Turnier
$h = $p('Kaputt H', 'keine-adresse');       // ungültig

foreach ([$a, $b, $h] as $pid) db_execute("INSERT INTO competition_player (competition_id, player_id) VALUES (?, ?)", [$cEin, $pid]);
$dbl = (int)db_insert("INSERT INTO `double` (tournament_id, player1_id, player2_id) VALUES (?, ?, ?)", [$tid, $c, $d]);
db_execute("INSERT INTO competition_double (competition_id, double_id) VALUES (?, ?)", [$cDop, $dbl]);
$team = (int)db_insert("INSERT INTO `team` (name) VALUES ('Team X')");
foreach ([$e, $f] as $pid) db_execute("INSERT INTO team_player (team_id, player_id) VALUES (?, ?)", [$team, $pid]);
db_execute("INSERT INTO competition_team (competition_id, team_id) VALUES (?, ?)", [$cTeam, $team]);
db_execute("INSERT INTO competition_player (competition_id, player_id) VALUES (?, ?)", [$cOth, $g]);

$mails = tournament_participant_emails($tid);
echo "tournament_participant_emails\n";
check('Einzel enthalten',          in_array('a@test.at', $mails, true));
check('beide Doppelpartner',       in_array('c@test.at', $mails, true) && in_array('D@Test.at', $mails, true));
check('Team-Mitglied enthalten',   in_array('e@test.at', $mails, true));
check('Duplikat (Groß/Klein) weg', count(array_filter($mails, fn($m) => strtolower($m) === 'a@test.at')) === 1);
check('fremdes Turnier nicht',     !in_array('g@test.at', $mails, true));
check('ungültig/leer übersprungen', !in_array('keine-adresse', $mails, true) && !in_array('', $mails, true));
check('genau 4 Adressen',          count($mails) === 4);
check('Turnier ohne Teilnehmer',   tournament_participant_emails((int)db_insert("INSERT INTO tournament (name) VALUES ('Leer')")) === []);

$db->rollBack();
echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
