<?php

// Anwurf-Zuordnung (Kickoff) für Team-Bewerbe.
// competition.kickoff_enabled = Option (0 = aus). Bei aktivierter Option wird je Begegnung
// festgelegt, welches Team Anwurf hat (match.kickoff_team_id): in der Gruppenphase zufällig,
// aber über den Spielplan ausgeglichen und ohne lange Serien; in KO-/Kreuzspielen je
// Begegnung zufällig (Einzelpartien). Idempotent/stabil — bestehende Anstöße bleiben.

/**
 * Weist allen Team-Gruppenbegegnungen eines Bewerbs ihr Anwurf-Team zu (idempotent).
 * Nach jedem (Neu-)Auslosen der Gruppen und nach Einstellungsänderungen aufrufen.
 *
 * Ziel: jedes Team hat (a) über alle Spiele ~gleich oft Anwurf und (b) den Anwurf
 * möglichst gleichmäßig über seinen Spielplan verteilt — also nicht mehrmals
 * hintereinander. Die Begegnungen werden je Gruppe in Rundenreihenfolge verarbeitet;
 * pro Begegnung erhält bevorzugt das Team den Anwurf, das in seiner vorigen Partie
 * KEINEN Anwurf hatte (Streak-Vermeidung). Bei gleicher Streak-Lage entscheidet der
 * Ausgleich der Gesamtzahl, sonst der Zufall.
 */
function assign_kickoff(int $cid): void {
    $c = db_fetch("SELECT is_team, kickoff_enabled FROM competition WHERE id=?", [$cid]);
    if (!$c || empty($c['is_team']) || empty($c['kickoff_enabled'])) {
        db_execute("UPDATE `match` SET kickoff_team_id=NULL WHERE competition_id=?", [$cid]);
        return;
    }

    // Anwurf nur bei Begegnungen mit zwei bekannten Teams; sonst leeren (z.B. noch offene
    // Bracket-Slots). Idempotent/stabil: bestehende Anstöße bleiben unverändert, damit
    // wiederholte Aufrufe (Auslosungen, Ergebnis-Propagation) nichts neu auswürfeln.
    db_execute(
        "UPDATE `match` SET kickoff_team_id=NULL
         WHERE competition_id=? AND (team1_id IS NULL OR team2_id IS NULL)",
        [$cid]
    );

    // 1) Gruppenphase: je Gruppe ausgeglichen + ohne lange Anwurf-Serien. Eine Gruppe wird
    //    nur (neu) verteilt, solange sie noch KEINEN Anwurf hat (frischer Draw) → stabil.
    foreach (db_fetchall("SELECT id FROM grp WHERE competition_id=?", [$cid]) as $g) {
        $already = (int)db_fetch(
            "SELECT COUNT(*) n FROM `match` WHERE group_id=? AND kickoff_team_id IS NOT NULL",
            [$g['id']]
        )['n'];
        if ($already > 0) continue;
        $matches = db_fetchall(
            "SELECT id, team1_id, team2_id, round_no FROM `match`
             WHERE group_id=? AND team1_id IS NOT NULL AND team2_id IS NOT NULL",
            [$g['id']]
        );
        if (!$matches) continue;

        // Mehrere Kandidaten erzeugen und den besten wählen (wenigste Anwurf-Serien +
        // bester Ausgleich). Abbruch sobald ein perfekter Kandidat (Score 0) gefunden ist.
        $best = null;
        $best_score = PHP_INT_MAX;
        for ($try = 0; $try < 400; $try++) {
            [$assign, $score] = _kickoff_candidate($matches);
            if ($score < $best_score) {
                $best = $assign;
                $best_score = $score;
                if ($score === 0) break;
            }
        }
        foreach ($best as $mid => $win) {
            db_execute("UPDATE `match` SET kickoff_team_id=? WHERE id=?", [$win, $mid]);
        }
    }

    // 2) KO-/Kreuzspiele (group_id IS NULL): pro Begegnung zufälliges Anwurf-Team — nur für
    //    noch nicht zugewiesene Begegnungen (stabil; neue Begegnungen entstehen beim Aufstieg).
    $kom = db_fetchall(
        "SELECT id, team1_id, team2_id FROM `match`
         WHERE competition_id=? AND group_id IS NULL
           AND team1_id IS NOT NULL AND team2_id IS NOT NULL AND kickoff_team_id IS NULL",
        [$cid]
    );
    foreach ($kom as $m) {
        $win = mt_rand(0, 1) ? (int)$m['team1_id'] : (int)$m['team2_id'];
        db_execute("UPDATE `match` SET kickoff_team_id=? WHERE id=?", [$win, $m['id']]);
    }
}

/**
 * Ein Anwurf-Kandidat für eine Gruppe (streak-bewusster Greedy mit Zufallsvariation).
 * @return array{0: array<int,int>, 1: int}  [matchId => kickoffTeamId], Score (niedriger = besser).
 *   Score = (Anzahl benachbart gleicher Anwurf-Zustände je Team) * 1000 + Ungleichgewicht.
 */
function _kickoff_candidate(array $matches): array {
    // Nach Runde sortieren (für die Streak-Logik), innerhalb einer Runde zufällig.
    foreach ($matches as &$mm) $mm['_rnd'] = mt_rand();
    unset($mm);
    usort($matches, fn($x, $y) =>
        [(int)$x['round_no'], $x['_rnd']] <=> [(int)$y['round_no'], $y['_rnd']]);

    $count    = [];   // team_id => bisherige Anstöße
    $lastKick = [];   // team_id => hatte in voriger Partie Anwurf?
    $seq      = [];   // team_id => Anwurf-Folge (0/1) in Rundenreihenfolge
    $assign   = [];   // match_id => Anwurf-Team
    foreach ($matches as $m) {
        $t1 = (int)$m['team1_id'];
        $t2 = (int)$m['team2_id'];
        // Score je Kandidat: niedriger = besser geeignet, den Anwurf zu erhalten.
        // Streak-Strafe (100) dominiert; Ausgleich der Gesamtzahl ist sekundär.
        $s1 = (!empty($lastKick[$t1]) ? 100 : 0) + (($count[$t1] ?? 0) - ($count[$t2] ?? 0));
        $s2 = (!empty($lastKick[$t2]) ? 100 : 0) + (($count[$t2] ?? 0) - ($count[$t1] ?? 0));
        if ($s1 < $s2)      $win = $t1;
        elseif ($s2 < $s1)  $win = $t2;
        else                $win = mt_rand(0, 1) ? $t1 : $t2;
        $lose = ($win === $t1) ? $t2 : $t1;

        $assign[(int)$m['id']] = $win;
        $count[$win]     = ($count[$win] ?? 0) + 1;
        $lastKick[$win]  = true;
        $lastKick[$lose] = false;
        $seq[$t1][] = ($win === $t1) ? 1 : 0;
        $seq[$t2][] = ($win === $t2) ? 1 : 0;
    }

    // Bewertung: benachbart gleiche Zustände (fehlende Abwechslung) + Ungleichgewicht.
    $adj = 0;
    $imb = 0;
    foreach ($seq as $arr) {
        $n = count($arr);
        for ($i = 1; $i < $n; $i++) if ($arr[$i] === $arr[$i - 1]) $adj++;
        $imb += abs(2 * array_sum($arr) - $n);   // 0 = perfekt hälftig
    }
    return [$assign, $adj * 1000 + $imb];
}
