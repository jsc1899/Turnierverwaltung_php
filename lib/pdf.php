<?php
require_once __DIR__ . '/../lib/standings.php';

// ── mPDF-Fabrik mit korrektem Temp-Verzeichnis ────────────────────────────────

function csv_safe(mixed $v): mixed {
    if (!is_string($v)) return $v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

// Dateinamen-tauglicher Slug (für gruppen-spezifische PDF-Dateinamen).
function _pdf_slug(string $s): string {
    $s = str_replace(['ä','ö','ü','Ä','Ö','Ü','ß'], ['ae','oe','ue','Ae','Oe','Ue','ss'], $s);
    $s = preg_replace('/[^A-Za-z0-9]+/', '_', $s);
    return trim($s, '_') ?: 'Gruppe';
}

function csv_row(array $cells): array {
    return array_map('csv_safe', $cells);
}

function mpdf(array $opts = []): \Mpdf\Mpdf {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf_tmp';
    if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
    return new \Mpdf\Mpdf(array_merge([
        'mode'    => 'utf-8',
        'format'  => 'A4',
        'tempDir' => $tempDir,
    ], $opts));
}

function pdf_css(): string {
    return '<style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111827; }
        h1    { font-size: 16pt; margin: 0 0 4mm; }
        h2    { font-size: 12pt; margin: 6mm 0 2mm; padding-bottom: 1mm;
                border-bottom: 0.5pt solid #9ca3af; color: #1e40af; }
        h3    { font-size: 10pt; margin: 4mm 0 1mm; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; font-size: 9pt; }
        th, td { border: 0.3pt solid #d1d5db; padding: 1.5mm 2.5mm; }
        th    { background: #eff6ff; font-weight: bold; }
        tr.odd { background: #f9fafb; }
        .winner { font-weight: bold; }
        .meta   { color: #6b7280; font-size: 9pt; margin-bottom: 5mm; }
        .badge-ok  { color: #15803d; }
        .badge-no  { color: #dc2626; }
        .badge-pen { color: #d97706; }
        .footer-note { margin-top: 8mm; font-size: 8pt; color: #6b7280; }
    </style>';
}

// ── Aushang: Turnier-Übersichtsblatt mit QR-Code ──────────────────────────────

function generate_aushang_pdf(int $tid): void {
    $t = db_fetch("SELECT * FROM tournament WHERE id=?", [$tid]);
    if (!$t) { http_response_code(404); exit; }

    $comps = db_fetchall(
        "SELECT * FROM competition WHERE tournament_id=? ORDER BY name", [$tid]
    );

    // QR-Code via SVG (kein GD erforderlich)
    // outputBase64=false → roher SVG-XML-String (nicht base64-Data-URI)
    $tour_url = APP_URL . '/tournament/' . $tid;
    $qr_html  = '';
    try {
        $qr_opts = new \chillerlan\QRCode\QROptions([
            'outputType'   => 'svg',
            'outputBase64' => false,
            'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::M,
        ]);
        $svg    = (new \chillerlan\QRCode\QRCode($qr_opts))->render($tour_url);
        $qr_tmp = sys_get_temp_dir() . '/qr_aushang_' . $tid . '.svg';
        file_put_contents($qr_tmp, $svg);
        $qr_html = '<img src="' . $qr_tmp . '" style="width:55mm;height:55mm;">';
    } catch (\Throwable) {
        $qr_html = '';
    }

    $html  = pdf_css();
    $html .= '<style>
        .header { background: #1a56db; color: #fff; padding: 6mm 12mm; margin: -5mm -5mm 0 -5mm; }
        .header h1 { color: #fff; font-size: 17pt; margin-bottom: 1mm; }
        .header .sub { color: #bfdbfe; font-size: 10pt; }
        .header-meta { width: 100%; border-collapse: collapse; }
        .header-meta td { border: none; padding: 0; vertical-align: middle; background: transparent; }
        .header-meta .date-cell { text-align: right; color: #bfdbfe; font-size: 10pt; white-space: nowrap; }
        .banner-img { text-align: center; margin-top: 8mm; margin-bottom: 5mm; }
        .qr-box { background: #eff6ff; border: 0.5pt solid #bfdbfe; border-radius: 3mm;
                  padding: 6mm; text-align: center; margin: 6mm 0; }
        .qr-box .label { font-size: 11pt; font-weight: bold; color: #1e40af; margin-bottom: 3mm; }
        .qr-url  { font-size: 8pt; color: #374151; margin-top: 2mm; word-break: break-all; }
        .footer-red { background: #fee2e2; color: #dc2626; font-weight: bold; font-size: 11pt;
                      text-align: center; padding: 4mm; margin: 6mm -5mm -5mm -5mm;
                      border-top: 1pt solid #fca5a5; }
    </style>';

    // Datum in deutschem Format für Header
    $datum = '';
    if ($t['event_date']) {
        $wochentage = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $ts    = strtotime($t['event_date']);
        $datum = $wochentage[(int)date('w', $ts)] . ', ' . date('d.m.Y', $ts);
    }

    // Header — Titel, Veranstalter links, Datum rechts im blauen Balken
    $html .= '<div class="header">';
    $html .= '<h1>' . e($t['name']) . '</h1>';
    if ($t['organizer'] || $datum) {
        $html .= '<table class="header-meta"><tr>'
            . '<td class="sub">' . ($t['organizer'] ? e($t['organizer']) : '') . '</td>'
            . '<td class="date-cell">' . $datum . '</td>'
            . '</tr></table>';
    }
    $html .= '</div>';

    // Banner-Bild (falls hochgeladen)
    if (!empty($t['banner_image'])) {
        $img_path = UPLOAD_DIR . $t['banner_image'];
        if (is_file($img_path)) {
            $html .= '<div class="banner-img"><img src="' . $img_path . '" style="max-width:170mm;max-height:60mm;"></div>';
        }
    }

    // Bewerbe-Liste — nur Name
    if ($comps) {
        $html .= '<h2>Bewerbe</h2>';
        $html .= '<ul style="padding-left:5mm;margin:0 0 4mm">';
        foreach ($comps as $c) {
            $html .= '<li style="padding:1.5mm 0;font-size:11pt">' . e($c['name']) . '</li>';
        }
        $html .= '</ul>';
    }

    // QR-Code-Box
    $reg_hint = 'QR-Code scannen – Ergebnisse verfolgen:';
    $html .= '<div class="qr-box">';
    $html .= '<div class="label">' . $reg_hint . '</div>';
    if ($qr_html) {
        $html .= $qr_html;
        $html .= '<div class="qr-url">' . e($tour_url) . '</div>';
    } else {
        $html .= '<div class="qr-url" style="font-size:10pt;padding:4mm 0">' . e($tour_url) . '</div>';
    }
    $html .= '</div>';

    // Hinweis-Fußzeile
    $html .= '<div class="footer-red">Bitte Ergebnisse unverzüglich dem Organisationsteam melden!</div>';

    $pdf = mpdf(['margin_top' => 5, 'margin_bottom' => 5]);
    $pdf->SetTitle('Aushang: ' . $t['name']);
    $pdf->WriteHTML($html);
    $safe = preg_replace('/[^\w\-]/', '_', $t['name'] ?: 'Turnier');
    $pdf->Output('Aushang_' . $safe . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Gruppenstand ──────────────────────────────────────────────────────────────

function generate_groups_pdf(int $cid, ?int $gid = null): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t          = db_fetch("SELECT name, sport FROM tournament WHERE id=?", [$c['tournament_id']]);
    $court      = court_label($t['sport'] ?? '');
    $grps       = $gid
        ? db_fetchall("SELECT * FROM grp WHERE competition_id=? AND id=?", [$cid, $gid])
        : db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);
    $team_size  = (int)($c['team_size'] ?? 0);
    $score_mode = $c['score_mode'] ?? 'match';
    $sets_mode_active = in_array($score_mode, ['sets', 'sets_grp']);
    $team_sum_mode = $is_team && ($c['team_result_mode'] ?? 'wins') === 'sum';
    $show_einzel = ($is_team && $team_size >= 2 && !$team_sum_mode) || $sets_mode_active;
    $grp_score_mode = $sets_mode_active ? 'sets' : 'match';
    $col_v   = $sets_mode_active ? 'V (Sätze)'    : 'V';
    $col_pm  = $sets_mode_active ? '+/- (Sätze)'  : '+/-';
    $col_ve  = $sets_mode_active ? 'V (Punkte)'   : 'V (Einzel)';
    $col_pme = $sets_mode_active ? '+/- (Punkte)' : '+/- (Einzel)';

    $html = pdf_css();
    if ($is_team && $team_size > 0) {
        $html .= '<style>
            .mblock { border:0.5pt solid #d1d5db; margin-bottom:4mm; page-break-inside:avoid; }
            .mblock-hdr { background:#eff6ff; padding:1mm 3mm; font-weight:bold; font-size:8pt;
                          color:#1e40af; border-bottom:0.3pt solid #bfdbfe; }
            .mtbl { width:100%; border-collapse:collapse; }
            .mtbl td { border:0.3pt solid #e5e7eb; padding:1.2mm 3mm; }
            .mnm-l { width:43%; text-align:right; font-weight:bold; font-size:9.5pt; background:#f8fafc; }
            .mnm-r { width:43%; font-weight:bold; font-size:9.5pt; background:#f8fafc; }
            .msc   { width:14%; text-align:center; font-size:10pt; background:#f8fafc; }
            .wline { border-bottom:0.6pt solid #374151; min-height:5mm; display:block; }
            .dlbl  { font-size:7.5pt; color:#6b7280; white-space:nowrap; }
        </style>';
    }
    $single_grp = ($gid && count($grps) === 1) ? $grps[0]['name'] : '';
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Gruppenstand'
           . ($single_grp ? ' — ' . e($single_grp) : '') . '</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    foreach ($grps as $gidx => $g) {
        if ($gidx > 0) $html .= '<pagebreak />';
        $html .= '<h3>' . e($g['name']) . '</h3>';
        if ($is_team) {
            $st = team_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
        } elseif ($is_doubles) {
            $st = double_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
        } else {
            $st = group_standings($g['id'], $c['seeding_order'] ?? 'desc', $grp_score_mode);
        }
        $nc = 'style="width:5%;text-align:center"';  // schmale Zahlenspalte
        $vc = 'style="width:8%;text-align:center"';  // V / +/- Spalte
        $html .= '<table>'
               . '<tr>'
               . '<th style="width:3%">#</th>'
               . '<th style="width:25%">Spieler</th>'
               . ($is_team ? '' : '<th style="width:16%">Verein</th>')
               . "<th $nc>Sp</th><th $nc>S</th><th $nc>U</th><th $nc>N</th>"
               . "<th $vc>" . $col_pm . '</th>';
        if ($show_einzel) {
            $html .= "<th $vc>" . $col_pme . '</th>';
        }
        $html .= "<th $nc>Pkt</th></tr>";
        foreach ($st as $i => $pl) {
            $odd = $i % 2 === 1 ? ' class="odd"' : '';
            $html .= "<tr$odd>"
                . '<td style="text-align:center">' . ($i+1) . '</td>'
                . '<td>' . e($pl['name']) . '</td>'
                . ($is_team ? '' : '<td>' . e($pl['club']) . '</td>')
                . '<td style="text-align:center">' . $pl['played'] . '</td>'
                . '<td style="text-align:center">' . $pl['wins'] . '</td>'
                . '<td style="text-align:center">' . $pl['draws'] . '</td>'
                . '<td style="text-align:center">' . $pl['losses'] . '</td>'
                . '<td style="text-align:center">' . ($pl['goal_diff'] >= 0 ? '+' : '') . $pl['goal_diff'] . '</td>';
            if ($show_einzel) {
                $ed = (int)($pl['einzel_diff'] ?? 0);
                $html .= '<td style="text-align:center">' . ($ed >= 0 ? '+' : '') . $ed . '</td>';
            }
            $html .= '<td style="text-align:center"><strong>' . $pl['points'] . '</strong></td></tr>';
        }
        $html .= '</table>';

        if ($is_team && $team_size > 0) {
            $gmatches = db_fetchall(
                "SELECT m.*, t1.name as p1name, t2.name as p2name
                 FROM `match` m
                 JOIN `team` t1 ON t1.id = m.team1_id
                 JOIN `team` t2 ON t2.id = m.team2_id
                 WHERE m.group_id = ?
                 ORDER BY m.match_order, m.id",
                [$g['id']]
            );
            if ($gmatches) {
                $html .= '<h4 style="font-size:9pt;color:#374151;margin:3mm 0 2mm">Spielpaarungen</h4>';
                foreach ($gmatches as $im => $gm) {
                    $sc = $gm['played']
                        ? $gm['score1'] . ' : ' . $gm['score2']
                        : '&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;';
                    // Duel-Ergebnisse laden
                    $duels = db_fetchall(
                        "SELECT d.*,
                         TRIM(CONCAT(COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'')!='', ' ',''), p1.name)) as player1_name,
                         TRIM(CONCAT(COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'')!='', ' ',''), p2.name)) as player2_name
                         FROM team_match_duel d
                         LEFT JOIN player p1 ON p1.id = d.player1_id
                         LEFT JOIN player p2 ON p2.id = d.player2_id
                         WHERE d.match_id = ?
                         ORDER BY d.duel_order",
                        [$gm['id']]
                    );
                    $html .= '<div class="mblock">'
                           . '<div class="mblock-hdr">Spiel ' . ($im + 1) . (!empty($gm['court_no']) ? ' &middot; ' . $court . ' ' . (int)$gm['court_no'] : '') . '</div>'
                           . '<table class="mtbl">'
                           . '<tr>'
                           . '<td class="mnm-l">' . e($gm['p1name']) . '</td>'
                           . '<td class="msc">' . $sc . '</td>'
                           . '<td class="mnm-r">' . e($gm['p2name']) . '</td>'
                           . '</tr>';
                    for ($di = 0; $di < $team_size; $di++) {
                        $d   = $duels[$di] ?? [];
                        $n1  = !empty($d['player1_name']) ? e($d['player1_name']) : '&nbsp;';
                        $n2  = !empty($d['player2_name']) ? e($d['player2_name']) : '&nbsp;';
                        $dsc = !empty($d['played']) ? $d['score1'] . ' : ' . $d['score2'] : '&nbsp;:&nbsp;';
                        $html .= '<tr>'
                               . '<td style="text-align:right;padding:1mm 3mm;font-size:8.5pt">' . $n1 . '</td>'
                               . '<td class="msc" style="font-size:9pt">' . $dsc . '</td>'
                               . '<td style="padding:1mm 3mm;font-size:8.5pt">' . $n2 . '</td>'
                               . '</tr>';
                    }
                    $html .= '</table></div>';
                }
            }
        } else {
            // Spielergebnisse für Einzel, Doppel und Team ohne Duelle
            if ($is_doubles) {
                $gmatches = db_fetchall(
                    "SELECT m.*,
                     COALESCE(CONCAT(p1a.name,' / ',p1b.name),'') as p1name,
                     COALESCE(CONCAT(p2a.name,' / ',p2b.name),'') as p2name
                     FROM `match` m
                     LEFT JOIN `double` d1 ON d1.id = m.double1_id
                     LEFT JOIN `double` d2 ON d2.id = m.double2_id
                     LEFT JOIN player p1a ON p1a.id = d1.player1_id
                     LEFT JOIN player p1b ON p1b.id = d1.player2_id
                     LEFT JOIN player p2a ON p2a.id = d2.player1_id
                     LEFT JOIN player p2b ON p2b.id = d2.player2_id
                     WHERE m.group_id = ?
                     ORDER BY m.match_order, m.id",
                    [$g['id']]
                );
            } elseif ($is_team) {
                $gmatches = db_fetchall(
                    "SELECT m.*, t1.name as p1name, t2.name as p2name
                     FROM `match` m
                     JOIN `team` t1 ON t1.id = m.team1_id
                     JOIN `team` t2 ON t2.id = m.team2_id
                     WHERE m.group_id = ?
                     ORDER BY m.match_order, m.id",
                    [$g['id']]
                );
            } else {
                $gmatches = db_fetchall(
                    "SELECT m.*,
                     TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name,
                     TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name
                     FROM `match` m
                     LEFT JOIN player p1 ON p1.id = m.player1_id
                     LEFT JOIN player p2 ON p2.id = m.player2_id
                     WHERE m.group_id = ?
                     ORDER BY m.match_order, m.id",
                    [$g['id']]
                );
            }
            $grp_sets = [];
            if ($sets_mode_active && $gmatches) {
                foreach (db_fetchall(
                    "SELECT ms.match_id, ms.score1, ms.score2
                     FROM match_set ms
                     JOIN `match` m ON m.id = ms.match_id
                     WHERE m.group_id = ?
                     ORDER BY ms.match_id, ms.set_order",
                    [$g['id']]
                ) as $s) {
                    $grp_sets[$s['match_id']][] = $s;
                }
            }
            if ($gmatches) {
                $score_col_lbl = $sets_mode_active ? 'Sätze' : 'Ergebnis';
                $html .= '<h4 style="font-size:9pt;color:#374151;margin:3mm 0 2mm">Spielergebnisse</h4>';
                $html .= '<table style="font-size:8.5pt">'
                       . '<tr>'
                       . '<th style="width:44%;text-align:right">Spieler 1</th>'
                       . '<th style="width:14%;text-align:center">' . $score_col_lbl . '</th>'
                       . '<th style="width:44%">Spieler 2</th>'
                       . '</tr>';
                // Spielfreie (Bye) je Runde: Gruppenmitglieder, die in der Runde fehlen
                // (bei garantierten Pausen / gerader Anzahl ggf. zwei).
                $show_byes  = !empty($c['show_byes']) || !empty($c['force_byes']);
                $round_byes = [];
                if ($show_byes) {
                    $bmembers = [];
                    foreach ($st as $sp) { $bmembers[(int)$sp['id']] = $sp['name']; }
                    $bpres = [];
                    foreach ($gmatches as $bm) {
                        $br = (int)($bm['round_no'] ?? 0);
                        if ($br <= 0) continue;
                        $p1 = (int)($bm['player1_id'] ?? $bm['double1_id'] ?? $bm['team1_id'] ?? 0);
                        $p2 = (int)($bm['player2_id'] ?? $bm['double2_id'] ?? $bm['team2_id'] ?? 0);
                        $bpres[$br][$p1] = true;
                        $bpres[$br][$p2] = true;
                    }
                    foreach ($bpres as $br => $pres) {
                        foreach ($bmembers as $bid => $bnm) { if (!isset($pres[$bid])) $round_byes[$br][] = $bnm; }
                    }
                }
                $bye_prev  = null;
                $bye_row   = function ($names) {
                    $h = '';
                    foreach ((array)$names as $nm) {
                        $h .= "<tr><td colspan='3' style='text-align:center;font-size:8pt;color:#6b7280;font-style:italic;padding:0.6mm 2mm'>" . e($nm) . ' &mdash; spielfrei</td></tr>';
                    }
                    return $h;
                };
                $round_row = fn($r) => "<tr><td colspan='3' style='text-align:center;font-size:8pt;font-weight:bold;color:#374151;background:#f3f4f6;padding:0.8mm 2mm'>Runde " . (int)$r . '</td></tr>';
                foreach ($gmatches as $i => $gm) {
                    $cur_round = (int)($gm['round_no'] ?? 0);
                    if ($show_byes && $cur_round !== $bye_prev) {
                        if ($bye_prev !== null && isset($round_byes[$bye_prev])) $html .= $bye_row($round_byes[$bye_prev]);
                        if ($cur_round > 0) $html .= $round_row($cur_round);
                    }
                    $bye_prev = $cur_round;
                    $odd = $i % 2 === 1 ? ' class="odd"' : '';
                    $sc  = $gm['played'] ? $gm['score1'] . ' : ' . $gm['score2'] : '— : —';
                    $court_pdf = !empty($gm['court_no']) ? "<div style='font-size:7pt;color:#6b7280'>" . $court . " " . (int)$gm['court_no'] . "</div>" : '';
                    $w1  = ($gm['played'] && $gm['score1'] > $gm['score2']) ? ' class="winner"' : '';
                    $w2  = ($gm['played'] && $gm['score2'] > $gm['score1']) ? ' class="winner"' : '';
                    $html .= "<tr$odd>"
                           . "<td style='text-align:right'$w1>" . e($gm['p1name']) . '</td>'
                           . "<td style='text-align:center'>$sc$court_pdf</td>"
                           . "<td$w2>" . e($gm['p2name']) . '</td>'
                           . '</tr>';
                    if ($sets_mode_active && $gm['played'] && !empty($grp_sets[$gm['id']])) {
                        $sets_str = implode('&nbsp;&nbsp;', array_map(
                            fn($sr) => $sr['score1'] . ':' . $sr['score2'],
                            $grp_sets[$gm['id']]
                        ));
                        $html .= "<tr><td colspan='3' style='text-align:center;font-size:7.5pt;color:#6b7280;padding:0.3mm 2mm'>Punkte: $sets_str</td></tr>";
                    }
                }
                if ($show_byes && $bye_prev !== null && isset($round_byes[$bye_prev])) {
                    $html .= $bye_row($round_byes[$bye_prev]);
                }
                $html .= '</table>';
            }
        }
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $fname = $single_grp ? 'Gruppenstand_' . _pdf_slug($single_grp) . '.pdf' : 'Gruppenstand.pdf';
    $pdf->Output($fname, \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Teampläne (pro Team eigener Spielplan zum Ausfüllen) ──────────────────────

function generate_team_strips_pdf(int $cid, ?int $gid = null): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    if (empty($c['is_team'])) {
        // Nur für Mannschaftsbewerbe sinnvoll.
        $pdf = mpdf();
        $pdf->WriteHTML(pdf_css() . '<h2>Teampläne</h2><p>Nur für Mannschaftsbewerbe verfügbar.</p>');
        $pdf->Output('Teamplaene.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }
    $t         = db_fetch("SELECT name, event_date, sport FROM tournament WHERE id=?", [$c['tournament_id']]);
    $tname     = $t ? $t['name'] : '';
    $court_ab  = court_abbr($t['sport'] ?? '');  // Kurzform für die „B"-Spalte (Spielplatz)
    $team_size = max(0, (int)($c['team_size'] ?? 0));
    $grps      = $gid
        ? db_fetchall("SELECT * FROM grp WHERE competition_id=? AND id=?", [$cid, $gid])
        : db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);
    // Datum = Turniertag (nicht das aktuelle Datum); leer, wenn kein Turnierdatum gesetzt.
    $datum = (!empty($t['event_date'])) ? date('d.m.Y', strtotime($t['event_date'])) : '';

    // Anzahl Einzelspiel-Spalten (1..n); mindestens 1 für die Optik.
    $ncols = max(1, $team_size);

    $html  = pdf_css();
    $html .= '<style>
        .strip-lbl { font-size:8pt; color:#1e40af; margin:0; }
        .strip-hd  { font-size:14pt; font-weight:bold; margin:0 0 3mm; color:#111827; }
        .stbl { width:100%; border-collapse:collapse; font-size:9.5pt; margin:0; }
        .stbl th, .stbl td { border:0.4pt solid #6b7280; padding:2mm 1mm; text-align:center; }
        .stbl th { background:#f3f4f6; font-weight:bold; }
        .stbl tr.alt td { background:#eceff3; }
        .stbl td.opp  { text-align:left; padding-left:3mm; font-weight:600; white-space:nowrap; }
        .stbl td.pause { text-align:left; padding-left:3mm; font-weight:bold; color:#374151; }
        .sfoot { width:100%; margin-top:5mm; font-size:9.5pt; color:#1e40af; border-collapse:collapse; }
        .sfoot td { border:none; padding:0; }
        .ksec-hd { font-size:12pt; font-weight:bold; margin:0 0 2mm; color:#111827; }
        .kstbl { width:100%; border-collapse:collapse; font-size:8.5pt; margin:0 0 3mm; }
        .kstbl th, .kstbl td { border:0.4pt solid #6b7280; padding:1.3mm 1mm; text-align:center; }
        .kstbl th { background:#f3f4f6; font-weight:bold; }
        .kstbl tr.alt td { background:#eceff3; }
        .kstbl td.tn-r { text-align:right; padding-right:2mm; font-weight:600; }
        .kstbl td.tn-l { text-align:left;  padding-left:2mm;  font-weight:600; }
        .kstbl td.mid  { border-left:0.8pt solid #374151; }
        .klbl { text-align:left; padding-left:2mm; white-space:nowrap; }
    </style>';

    // Wiederverwendbare Kopfzeile (Einzelspiel-Spalten + Su/Pu).
    $score_head = '';
    for ($i = 1; $i <= $ncols; $i++) $score_head .= '<th style="width:9mm">' . $i . '</th>';
    $score_head .= '<th style="width:11mm">Su</th><th style="width:11mm">Pu</th>';
    // Leere Score-Zellen (zum Ausfüllen).
    $score_cells = str_repeat('<td>&nbsp;</td>', $ncols + 2);

    $first = true;
    foreach ($grps as $g) {
        $numbers = team_start_numbers($g['id']);  // [team_id => 1..N]
        if (!$numbers) continue;
        $names = [];
        foreach (db_fetchall("SELECT t.id, t.name FROM `team` t JOIN group_team gt ON gt.team_id=t.id WHERE gt.group_id=?", [$g['id']]) as $r) {
            $names[(int)$r['id']] = $r['name'];
        }
        // Alle Team-Begegnungen der Gruppe.
        $matches = db_fetchall(
            "SELECT id, team1_id, team2_id, round_no, court_no, kickoff_team_id
             FROM `match`
             WHERE group_id=? AND team1_id IS NOT NULL AND team2_id IS NOT NULL
             ORDER BY round_no, match_order, id",
            [$g['id']]
        );
        // Pro Team alle Begegnungen sammeln + maximale Rundenzahl bestimmen.
        // Mit Rundeninfo (round_no) werden Spielfrei-Runden als „Pause" eingefügt; ohne
        // Rundeninfo (alte Daten) werden die Begegnungen einfach der Reihe nach gelistet.
        $teamMatches = [];
        $maxRound = 0;
        foreach ($matches as $m) {
            $r = (int)$m['round_no'];
            if ($r > $maxRound) $maxRound = $r;
            $teamMatches[(int)$m['team1_id']][] = $m;
            $teamMatches[(int)$m['team2_id']][] = $m;
        }
        $useRounds = $maxRound > 0;

        // Teams in Start-Nr.-Reihenfolge.
        asort($numbers);
        foreach ($numbers as $tid => $nr) {
            if (!$first) $html .= '<pagebreak />';
            $first = false;

            $html .= '<p class="strip-lbl">Start-Nr.</p>';
            $html .= '<p class="strip-hd">#' . $nr . ' &ndash; ' . e($names[$tid] ?? ('Team ' . $nr)) . '</p>';

            $html .= '<table class="stbl"><thead><tr>'
                   . '<th style="width:9mm">Dg</th>'
                   . '<th style="width:9mm">' . $court_ab . '</th>'
                   . '<th style="width:9mm">Ge</th>'
                   . '<th style="width:9mm">An</th>'
                   . $score_head
                   . '<th>Mannschaft</th>'
                   . $score_head
                   . '</tr></thead><tbody>';

            // Zeilen bestimmen: ['dg' => int|'', 'm' => Begegnung|null (Pause)].
            $rows = [];
            if ($useRounds) {
                $byRound = [];
                foreach ($teamMatches[$tid] ?? [] as $tm) $byRound[(int)$tm['round_no']] = $tm;
                for ($r = 1; $r <= $maxRound; $r++) {
                    $rows[] = ['dg' => $r, 'm' => $byRound[$r] ?? null];
                }
            } else {
                foreach ($teamMatches[$tid] ?? [] as $tm) {
                    $rows[] = ['dg' => '', 'm' => $tm];
                }
            }

            $rowi = 0;
            foreach ($rows as $row) {
                $alt = ($rowi % 2 === 1) ? ' class="alt"' : '';
                $rowi++;
                $dg = $row['dg'] !== '' ? $row['dg'] : '&nbsp;';
                $m  = $row['m'];
                if (!$m) {
                    // Spielfrei in dieser Runde.
                    $html .= '<tr' . $alt . '>'
                           . '<td>' . $dg . '</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>'
                           . $score_cells
                           . '<td class="pause">Pause</td>'
                           . $score_cells
                           . '</tr>';
                    continue;
                }
                $opp   = ((int)$m['team1_id'] === $tid) ? (int)$m['team2_id'] : (int)$m['team1_id'];
                $geNr  = $numbers[$opp] ?? '';
                $koNr  = !empty($m['kickoff_team_id']) ? ($numbers[(int)$m['kickoff_team_id']] ?? '') : '';
                $court = !empty($m['court_no']) ? (int)$m['court_no'] : '';
                $oppLabel = 'T' . $geNr . ' &ndash; ' . e($names[$opp] ?? '');
                $html .= '<tr' . $alt . '>'
                       . '<td>' . $dg . '</td>'
                       . '<td>' . ($court !== '' ? $court : '&nbsp;') . '</td>'
                       . '<td>' . ($geNr !== '' ? $geNr : '&nbsp;') . '</td>'
                       . '<td>' . ($koNr !== '' ? $koNr : '&nbsp;') . '</td>'
                       . $score_cells
                       . '<td class="opp">' . $oppLabel . '</td>'
                       . $score_cells
                       . '</tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<table class="sfoot"><tr>'
                   . '<td style="text-align:left">Veranstaltung: ' . e($tname) . ' &ndash; ' . e($g['name']) . '</td>'
                   . '<td style="text-align:right">Datum: ' . $datum . '</td>'
                   . '</tr></table>';
        }
    }

    // ── KO-/Kreuzspiel-Begegnungen: kompakte Strips, möglichst viele je Seite ──
    $kom = db_fetchall(
        "SELECT m.id, m.bracket, m.ko_round, m.ko_position, m.court_no, m.place_lo,
                t1.name AS p1name, t2.name AS p2name
         FROM `match` m
         LEFT JOIN `team` t1 ON t1.id = m.team1_id
         LEFT JOIN `team` t2 ON t2.id = m.team2_id
         WHERE m.competition_id=? AND m.group_id IS NULL
           AND m.team1_id IS NOT NULL AND m.team2_id IS NOT NULL",
        [$cid]
    );
    if ($kom) {
        // Blockgröße S je Kreuzspiel-Block (für die Platzbereichs-Labels).
        $blockS = [];
        foreach ($kom as $m) {
            if ($m['bracket'] !== null && str_starts_with((string)$m['bracket'], 'C') && (int)$m['ko_round'] === 1) {
                $blockS[$m['bracket']] = ($blockS[$m['bracket']] ?? 0) + 2;
            }
        }
        $round_names = [2=>'Finale', 4=>'Halbfinale', 8=>'Viertelfinale', 16=>'Achtelfinale',
                        32=>'Runde der 32', 64=>'Runde der 64', 3=>'Spiel um Platz 3'];
        $label = function($m) use ($round_names, $blockS) {
            $b = $m['bracket'];
            if ($b === null) return $round_names[(int)$m['ko_round']] ?? ('Runde ' . (int)$m['ko_round']);
            if ($b === 'GF') return 'Grand Final';
            if ($b === 'W')  return 'WB R' . (int)$m['ko_round'];
            if ($b === 'L')  return 'LB R' . (int)$m['ko_round'];
            if (str_starts_with((string)$b, 'C')) {
                $S  = $blockS[$b] ?? 0;
                $ps = $S > 0 ? ($S >> ((int)$m['ko_round'] - 1)) : 0;
                $lo = (int)$m['place_lo'];
                if ($ps >= 2) { $hi = $lo + $ps - 1; return $ps <= 2 ? "Pl. {$lo}/{$hi}" : "Pl. {$lo}–{$hi}"; }
                return 'Pl. ' . $lo;
            }
            return '';
        };
        $grpkey = fn($b) => $b === null ? 0 : (in_array($b, ['W','L','GF'], true) ? 1 : 2);
        usort($kom, function($a, $b) use ($grpkey) {
            $ga = $grpkey($a['bracket']); $gb = $grpkey($b['bracket']);
            if ($ga !== $gb) return $ga <=> $gb;
            if ($a['bracket'] === null) {   // Einzel-KO: chronologisch (höhere ko_round zuerst)
                if ((int)$a['ko_round'] !== (int)$b['ko_round']) return (int)$b['ko_round'] <=> (int)$a['ko_round'];
                return (int)$a['ko_position'] <=> (int)$b['ko_position'];
            }
            if ($a['bracket'] !== $b['bracket']) return strcmp((string)$a['bracket'], (string)$b['bracket']);
            if ((int)$a['ko_round'] !== (int)$b['ko_round']) return (int)$a['ko_round'] <=> (int)$b['ko_round'];
            return (int)$a['ko_position'] <=> (int)$b['ko_position'];
        });

        // Zweite Score-Spaltengruppe mit Trennlinie (Mannschaft 2).
        $sc_head_r = '<th class="mid" style="width:9mm">1</th>';
        for ($i = 2; $i <= $ncols; $i++) $sc_head_r .= '<th style="width:9mm">' . $i . '</th>';
        $sc_head_r .= '<th style="width:11mm">Su</th><th style="width:11mm">Pu</th>';
        $sc_cells_r = '<td class="mid">&nbsp;</td>' . str_repeat('<td>&nbsp;</td>', $ncols + 1);

        $sections = [
            'KO-Phase'    => array_values(array_filter($kom, fn($m) => $grpkey($m['bracket']) < 2)),
            'Kreuzspiele' => array_values(array_filter($kom, fn($m) => $grpkey($m['bracket']) === 2)),
        ];
        foreach ($sections as $secname => $sms) {
            if (!$sms) continue;
            if (!$first) $html .= '<pagebreak />';
            $first = false;
            $html .= '<p class="ksec-hd">' . e($c['name']) . ' &ndash; ' . $secname . '</p>';
            $html .= '<table class="kstbl"><thead><tr>'
                   . '<th class="klbl" style="width:24mm">Spiel</th>'
                   . '<th style="width:8mm">' . $court_ab . '</th>'
                   . '<th style="width:21%">Mannschaft 1</th>'
                   . $score_head
                   . '<th class="mid" style="width:21%">Mannschaft 2</th>'
                   . $sc_head_r
                   . '</tr></thead><tbody>';
            foreach ($sms as $i => $m) {
                $alt   = ($i % 2 === 1) ? ' class="alt"' : '';
                $court = !empty($m['court_no']) ? (int)$m['court_no'] : '&nbsp;';
                $html .= '<tr' . $alt . '>'
                       . '<td class="klbl">' . e($label($m)) . '</td>'
                       . '<td>' . $court . '</td>'
                       . '<td class="tn-r">' . e($m['p1name'] ?? '') . '</td>'
                       . $score_cells
                       . '<td class="tn-l mid">' . e($m['p2name'] ?? '') . '</td>'
                       . $sc_cells_r
                       . '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '<table class="sfoot"><tr>'
                   . '<td style="text-align:left">Veranstaltung: ' . e($tname) . '</td>'
                   . '<td style="text-align:right">Datum: ' . $datum . '</td>'
                   . '</tr></table>';
        }
    }

    if ($first) {
        // Keine Teams/Gruppen/Begegnungen vorhanden.
        $html .= '<h2>Teampläne</h2><p>Keine ausgelosten Begegnungen vorhanden.</p>';
    }

    $pdf = mpdf(['format' => 'A4-L', 'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 10, 'margin_right' => 10]);
    $pdf->SetTitle('Teampläne: ' . $c['name']);
    $pdf->WriteHTML($html);
    $pdf->Output('Teamplaene.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── KO-Bracket ────────────────────────────────────────────────────────────────

function generate_ko_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t = db_fetch("SELECT name, sport FROM tournament WHERE id=?", [$c['tournament_id']]);
    $court = court_label($t['sport'] ?? '');

    if ($c['mode'] === 'double_ko') {
        require_once __DIR__ . '/double_ko_bracket.php';
        _generate_dko_bracket_pdf($c, $t);
        return;
    }

    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);
    if ($is_team) {
        $matches = db_fetchall(
            "SELECT m.*, t1.name as p1name, t2.name as p2name
             FROM `match` m
             LEFT JOIN `team` t1 ON t1.id=m.team1_id
             LEFT JOIN `team` t2 ON t2.id=m.team2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY m.ko_round DESC, m.ko_position",
            [$cid]
        );
    } elseif ($is_doubles) {
        $matches = db_fetchall(
            "SELECT m.*, d1.name as p1name, d2.name as p2name
             FROM `match` m
             LEFT JOIN `double` d1 ON d1.id=m.double1_id
             LEFT JOIN `double` d2 ON d2.id=m.double2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY m.ko_round DESC, m.ko_position",
            [$cid]
        );
    } else {
        $matches = db_fetchall(
            "SELECT m.*,
             TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name,
             TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name
             FROM `match` m
             LEFT JOIN player p1 ON p1.id=m.player1_id
             LEFT JOIN player p2 ON p2.id=m.player2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY m.ko_round DESC, m.ko_position",
            [$cid]
        );
    }
    $score_mode   = $c['score_mode'] ?? 'match';
    $sets_mode_ko = ($score_mode === 'sets');
    $score_label  = $sets_mode_ko ? 'Sätze' : 'Ergebnis';

    $round_names = [
        2=>'Finale', 4=>'Halbfinale', 8=>'Viertelfinale',
        16=>'Achtelfinale', 32=>'Runde der 32', 64=>'Runde der 64', 3=>'Platz 3',
    ];
    $rounds = [];
    foreach ($matches as $m) { $rounds[(int)$m['ko_round']][] = $m; }
    krsort($rounds);

    $match_sets = [];
    if ($sets_mode_ko) {
        foreach (db_fetchall(
            "SELECT ms.match_id, ms.score1, ms.score2
             FROM match_set ms
             JOIN `match` m ON m.id = ms.match_id
             WHERE m.competition_id = ? AND m.group_id IS NULL
             ORDER BY ms.match_id, ms.set_order",
            [$cid]
        ) as $s) {
            $match_sets[$s['match_id']][] = $s;
        }
    }

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — KO-Phase</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    foreach ($rounds as $r => $rmatches) {
        $html .= '<h3>' . ($round_names[$r] ?? "Runde $r") . '</h3>';
        $html .= '<table><tr><th>Spieler 1</th><th style="width:20mm;text-align:center">' . $score_label . '</th><th>Spieler 2</th></tr>';
        foreach ($rmatches as $i => $m) {
            $odd = $i % 2 === 1 ? ' class="odd"' : '';
            $s   = $m['played'] ? $m['score1'] . ' : ' . $m['score2'] : '— : —';
            $court_ko = !empty($m['court_no']) ? "<div style='font-size:7pt;color:#6b7280'>" . $court . " " . (int)$m['court_no'] . "</div>" : '';
            $c1  = ($m['played'] && $m['score1'] > $m['score2']) ? ' class="winner"' : '';
            $c2  = ($m['played'] && $m['score2'] > $m['score1']) ? ' class="winner"' : '';
            $p1  = e($m['p1name'] ?: 'Freilos');
            $p2  = e($m['p2name'] ?: 'Freilos');
            $html .= "<tr$odd><td$c1>$p1</td><td style='text-align:center'>$s$court_ko</td><td$c2>$p2</td></tr>";
            if ($sets_mode_ko && !empty($match_sets[$m['id']])) {
                $sets_str = implode('&nbsp;&nbsp;', array_map(fn($sr) => $sr['score1'].':'.$sr['score2'], $match_sets[$m['id']]));
                $html .= "<tr><td colspan='3' style='text-align:center;font-size:8pt;color:#6b7280;padding:0.5mm 2mm'>Punkte: $sets_str</td></tr>";
            }
        }
        $html .= '</table>';
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('KO-Phase.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

function _generate_dko_bracket_pdf(array $c, ?array $t): void {
    $cid = (int)$c['id'];
    $court = court_label($t['sport'] ?? '');
    $cap = _dko_cap($cid);
    $k   = $cap > 0 ? (int)log($cap, 2) : 0;
    $lb_total = max(0, 2 * ($k - 1));

    $wb_names = [];
    for ($r = 1; $r <= $k; $r++) {
        $wb_names[$r] = match ($k - $r) {
            0 => 'WB Finale', 1 => 'WB Halbfinale', 2 => 'WB Viertelfinale',
            3 => 'WB Achtelfinale', default => 'WB Runde ' . $r,
        };
    }
    $lb_names = [];
    for ($r = 1; $r <= $lb_total; $r++) {
        $lb_names[$r] = $r === $lb_total ? 'LB Finale'
            : ($r === $lb_total - 1 ? 'LB Halbfinale' : 'LB Runde ' . $r);
    }

    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);
    if ($is_team) {
        $matches = db_fetchall(
            "SELECT m.*, t1.name as p1name, t2.name as p2name
             FROM `match` m
             LEFT JOIN `team` t1 ON t1.id=m.team1_id
             LEFT JOIN `team` t2 ON t2.id=m.team2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY FIELD(m.bracket,'W','L','GF'), m.ko_round, m.ko_position",
            [$cid]
        );
    } elseif ($is_doubles) {
        $matches = db_fetchall(
            "SELECT m.*, d1.name as p1name, d2.name as p2name
             FROM `match` m
             LEFT JOIN `double` d1 ON d1.id=m.double1_id
             LEFT JOIN `double` d2 ON d2.id=m.double2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY FIELD(m.bracket,'W','L','GF'), m.ko_round, m.ko_position",
            [$cid]
        );
    } else {
        $matches = db_fetchall(
            "SELECT m.*,
             TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name,
             TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name
             FROM `match` m
             LEFT JOIN player p1 ON p1.id=m.player1_id
             LEFT JOIN player p2 ON p2.id=m.player2_id
             WHERE m.competition_id=? AND m.group_id IS NULL
             ORDER BY FIELD(m.bracket,'W','L','GF'), m.ko_round, m.ko_position",
            [$cid]
        );
    }

    $wb_rounds = []; $lb_rounds = []; $gf = null;
    foreach ($matches as $m) {
        $r = (int)$m['ko_round'];
        if ($m['bracket'] === 'W')       $wb_rounds[$r][] = $m;
        elseif ($m['bracket'] === 'L')   $lb_rounds[$r][] = $m;
        elseif ($m['bracket'] === 'GF')  $gf = $m;
    }
    ksort($wb_rounds); ksort($lb_rounds);

    $score_mode   = $c['score_mode'] ?? 'match';
    $sets_mode_ko = ($score_mode === 'sets');
    $score_label  = $sets_mode_ko ? 'Sätze' : 'Ergebnis';

    $match_sets = [];
    if ($sets_mode_ko) {
        foreach (db_fetchall(
            "SELECT ms.match_id, ms.score1, ms.score2
             FROM match_set ms
             JOIN `match` m ON m.id = ms.match_id
             WHERE m.competition_id = ? AND m.group_id IS NULL
             ORDER BY ms.match_id, ms.set_order",
            [$cid]
        ) as $s) {
            $match_sets[$s['match_id']][] = $s;
        }
    }

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Doppel-KO</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    $row_html = function(array $m, int $i) use (&$html, $sets_mode_ko, $match_sets): void {
        $odd = $i % 2 === 1 ? ' class="odd"' : '';
        $s   = $m['played'] ? $m['score1'] . ' : ' . $m['score2'] : '— : —';
        $court_dko = !empty($m['court_no']) ? "<div style='font-size:7pt;color:#6b7280'>" . $court . " " . (int)$m['court_no'] . "</div>" : '';
        $c1  = ($m['played'] && $m['score1'] > $m['score2']) ? ' class="winner"' : '';
        $c2  = ($m['played'] && $m['score2'] > $m['score1']) ? ' class="winner"' : '';
        $html .= "<tr$odd><td$c1>" . e($m['p1name'] ?: '—') . "</td>"
               . "<td style='text-align:center'>$s$court_dko</td>"
               . "<td$c2>" . e($m['p2name'] ?: '—') . "</td></tr>";
        if ($sets_mode_ko && !empty($match_sets[$m['id']])) {
            $sets_str = implode('&nbsp;&nbsp;', array_map(fn($sr) => $sr['score1'].':'.$sr['score2'], $match_sets[$m['id']]));
            $html .= "<tr><td colspan='3' style='text-align:center;font-size:8pt;color:#6b7280;padding:0.5mm 2mm'>Punkte: $sets_str</td></tr>";
        }
    };
    $tbl_open  = '<table><tr><th>Spieler 1</th><th style="width:20mm;text-align:center">' . $score_label . '</th><th>Spieler 2</th></tr>';
    $tbl_close = '</table>';

    if ($wb_rounds) {
        $html .= '<h3>Winners Bracket</h3>';
        foreach ($wb_rounds as $r => $rmatches) {
            $html .= '<h4 style="margin:.5em 0 .3em">' . ($wb_names[$r] ?? 'WB Runde ' . $r) . '</h4>' . $tbl_open;
            foreach ($rmatches as $i => $m) $row_html($m, $i);
            $html .= $tbl_close;
        }
    }
    if ($lb_rounds) {
        $html .= '<h3 style="margin-top:8mm">Losers Bracket</h3>';
        foreach ($lb_rounds as $r => $rmatches) {
            $html .= '<h4 style="margin:.5em 0 .3em">' . ($lb_names[$r] ?? 'LB Runde ' . $r) . '</h4>' . $tbl_open;
            foreach ($rmatches as $i => $m) $row_html($m, $i);
            $html .= $tbl_close;
        }
    }
    if ($gf) {
        $html .= '<h3 style="margin-top:8mm">Großes Finale</h3>' . $tbl_open;
        $row_html($gf, 0);
        $html .= $tbl_close;
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('Doppel-KO.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Kreuzspiele / Platzierungs-Brackets (Modus groups_cross) ──────────────────

function generate_cross_pdf(int $cid): void {
    require_once __DIR__ . '/placement_bracket.php';
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$c['tournament_id']]);
    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);

    if ($is_team) {
        $cm = db_fetchall("SELECT m.*, COALESCE(t1.name,'') p1name, COALESCE(t2.name,'') p2name
            FROM `match` m LEFT JOIN `team` t1 ON t1.id=m.team1_id LEFT JOIN `team` t2 ON t2.id=m.team2_id
            WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
            ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
        $cm = array_map(fn($m) => $m + ['p1id' => $m['team1_id'], 'p2id' => $m['team2_id']], $cm);
    } elseif ($is_doubles) {
        $cm = db_fetchall("SELECT m.*, COALESCE(CONCAT(dp1a.name,' / ',dp1b.name),'') p1name,
            COALESCE(CONCAT(dp2a.name,' / ',dp2b.name),'') p2name
            FROM `match` m LEFT JOIN `double` d1 ON d1.id=m.double1_id LEFT JOIN `double` d2 ON d2.id=m.double2_id
            LEFT JOIN player dp1a ON dp1a.id=d1.player1_id LEFT JOIN player dp1b ON dp1b.id=d1.player2_id
            LEFT JOIN player dp2a ON dp2a.id=d2.player1_id LEFT JOIN player dp2b ON dp2b.id=d2.player2_id
            WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
            ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
        $cm = array_map(fn($m) => $m + ['p1id' => $m['double1_id'], 'p2id' => $m['double2_id']], $cm);
    } else {
        $cm = db_fetchall("SELECT m.*,
            TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) p1name,
            TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) p2name
            FROM `match` m LEFT JOIN player p1 ON p1.id=m.player1_id LEFT JOIN player p2 ON p2.id=m.player2_id
            WHERE m.competition_id=? AND m.group_id IS NULL AND m.bracket LIKE 'C%'
            ORDER BY m.bracket, m.ko_round, m.ko_position", [$cid]);
        $cm = array_map(fn($m) => $m + ['p1id' => $m['player1_id'], 'p2id' => $m['player2_id']], $cm);
    }

    $byblock = []; $nameById = [];
    foreach ($cm as $m) {
        $byblock[$m['bracket']][] = $m;
        if (!empty($m['p1id'])) $nameById[(int)$m['p1id']] = $m['p1name'];
        if (!empty($m['p2id'])) $nameById[(int)$m['p2id']] = $m['p2name'];
    }

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Kreuzspiele / Platzierungen</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    // Endplatzierung
    $finals = placement_final_places($cid);
    if ($finals) {
        $html .= '<h3>Endplatzierung</h3><table><tr><th style="width:14mm">Platz</th><th>Teilnehmer</th></tr>';
        foreach ($finals as $place => $pid) {
            $html .= '<tr><td style="text-align:center">' . (int)$place . '</td><td>' . e($nameById[(int)$pid] ?? '?') . '</td></tr>';
        }
        $html .= '</table>';
    }

    foreach ($byblock as $tag => $ms) {
        $S = 2 * count(array_filter($ms, fn($x) => (int)$x['ko_round'] === 1));
        if ($S < 2) continue;
        $r1 = array_values(array_filter($ms, fn($x) => (int)$x['ko_round'] === 1));
        $blockLo = (int)$r1[0]['place_lo'];
        $real = 0; foreach ($r1 as $x) { if (!empty($x['p1id'])) $real++; if (!empty($x['p2id'])) $real++; }
        $hi = $blockLo + max(1, $real) - 1;
        $html .= '<h3 style="page-break-before:always">' . ($real > 1 ? "Plätze {$blockLo}–{$hi}" : "Platz {$blockLo}") . '</h3>';

        $rounds = [];
        foreach ($ms as $m) { $rounds[(int)$m['ko_round']][] = $m; }
        ksort($rounds);
        foreach ($rounds as $r => $rms) {
            $ps = $S >> ($r - 1);
            $html .= '<h4 style="font-size:9pt;color:#374151;margin:3mm 0 1mm">Runde ' . (int)$r . '</h4>';
            $html .= '<table style="font-size:9pt"><tr><th style="width:22mm">Plätze</th><th style="width:40%;text-align:right">Teilnehmer 1</th><th style="width:16mm;text-align:center">Erg.</th><th>Teilnehmer 2</th></tr>';
            foreach ($rms as $m) {
                $h1 = !empty($m['p1id']); $h2 = !empty($m['p2id']);
                if (!$h1 && !$h2) continue;
                $lo = (int)$m['place_lo']; $ph = $lo + $ps - 1;
                $rng = $ps <= 2 ? "{$lo}/{$ph}" : "{$lo}–{$ph}";
                $sc  = $m['played'] ? (int)$m['score1'] . ' : ' . (int)$m['score2'] : '— : —';
                $n1  = $h1 ? e($m['p1name']) : '—';
                $n2  = $h2 ? e($m['p2name']) : 'Freilos';
                $html .= "<tr><td style='text-align:center'>$rng</td><td style='text-align:right'>$n1</td><td style='text-align:center'>$sc</td><td>$n2</td></tr>";
            }
            $html .= '</table>';
        }
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('Platzierungen.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Match-Cards (zum Ausdrucken) ──────────────────────────────────────────────

function generate_match_cards_pdf(int $cid, ?int $gid = null): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $court = court_label(db_fetch("SELECT sport FROM tournament WHERE id=?", [$c['tournament_id']])['sport'] ?? '');
    $card_grp = $gid ? db_fetch("SELECT name FROM grp WHERE id=? AND competition_id=?", [$gid, $cid]) : null;
    $card_grp_name = $card_grp['name'] ?? '';

    $is_dko = ($c['mode'] === 'double_ko');

    // Für DKO: sequentielle Spielnummern + Rundenbezeichnungen vorberechnen
    $dko_seq    = [];
    $dko_labels = [];
    if ($is_dko) {
        require_once __DIR__ . '/double_ko_bracket.php';
        $cap      = _dko_cap($cid);
        $k        = $cap > 0 ? (int)log($cap, 2) : 0;
        $lb_total = max(0, 2 * ($k - 1));
        $wb_names = [];
        for ($r = 1; $r <= $k; $r++) {
            $wb_names[$r] = match ($k - $r) {
                0 => 'WB Finale', 1 => 'WB Halbfinale', 2 => 'WB Viertelfinale',
                3 => 'WB Achtelfinale', default => 'WB Runde ' . $r,
            };
        }
        $lb_names = [];
        for ($r = 1; $r <= $lb_total; $r++) {
            $lb_names[$r] = $r === $lb_total ? 'LB Finale'
                : ($r === $lb_total - 1 ? 'LB Halbfinale' : 'LB Runde ' . $r);
        }
        $seq = 0;
        foreach (db_fetchall(
            "SELECT id, bracket, ko_round FROM `match`
             WHERE competition_id=? AND group_id IS NULL
             ORDER BY FIELD(bracket,'W','L','GF'), ko_round, ko_position",
            [$cid]
        ) as $am) {
            $seq++;
            $dko_seq[$am['id']] = $seq;
            $r = (int)$am['ko_round'];
            $dko_labels[$am['id']] = match ($am['bracket']) {
                'W'  => $wb_names[$r] ?? 'WB Runde ' . $r,
                'L'  => $lb_names[$r] ?? 'LB Runde ' . $r,
                default => 'Großes Finale',
            };
        }
    }

    $is_team    = !empty($c['is_team']);
    $is_doubles = !$is_team && !empty($c['is_doubles']);
    $order   = $is_dko
        ? "FIELD(m.bracket,'W','L','GF'), m.ko_round, m.ko_position"
        : "g.name, m.match_order, m.id";
    if ($is_team) {
        $matches = db_fetchall(
            "SELECT m.*,
             t1.name as p1name, '' as p1club,
             t2.name as p2name, '' as p2club,
             g.name as group_name
             FROM `match` m
             LEFT JOIN `team` t1 ON t1.id=m.team1_id
             LEFT JOIN `team` t2 ON t2.id=m.team2_id
             LEFT JOIN grp g ON g.id=m.group_id
             WHERE m.competition_id=? AND m.team1_id IS NOT NULL AND m.team2_id IS NOT NULL
               AND m.played=0
             ORDER BY $order",
            [$cid]
        );
    } elseif ($is_doubles) {
        $matches = db_fetchall(
            "SELECT m.*,
             d1.name as p1name,
             CASE WHEN COALESCE(dp1a.club,'')='' THEN COALESCE(dp1b.club,'')
                  WHEN COALESCE(dp1b.club,'')='' THEN COALESCE(dp1a.club,'')
                  WHEN dp1a.club=dp1b.club THEN dp1a.club
                  ELSE CONCAT(dp1a.club,' / ',dp1b.club) END as p1club,
             d2.name as p2name,
             CASE WHEN COALESCE(dp2a.club,'')='' THEN COALESCE(dp2b.club,'')
                  WHEN COALESCE(dp2b.club,'')='' THEN COALESCE(dp2a.club,'')
                  WHEN dp2a.club=dp2b.club THEN dp2a.club
                  ELSE CONCAT(dp2a.club,' / ',dp2b.club) END as p2club,
             g.name as group_name
             FROM `match` m
             LEFT JOIN `double` d1 ON d1.id=m.double1_id
             LEFT JOIN `double` d2 ON d2.id=m.double2_id
             LEFT JOIN player dp1a ON dp1a.id=d1.player1_id
             LEFT JOIN player dp1b ON dp1b.id=d1.player2_id
             LEFT JOIN player dp2a ON dp2a.id=d2.player1_id
             LEFT JOIN player dp2b ON dp2b.id=d2.player2_id
             LEFT JOIN grp g ON g.id=m.group_id
             WHERE m.competition_id=? AND m.double1_id IS NOT NULL AND m.double2_id IS NOT NULL
               AND m.played=0
             ORDER BY $order",
            [$cid]
        );
    } else {
        $matches = db_fetchall(
            "SELECT m.*,
             TRIM(CONCAT(p1.name,IF(COALESCE(p1.firstname,'')!='',CONCAT(' ',p1.firstname),''))) as p1name, p1.club as p1club,
             TRIM(CONCAT(p2.name,IF(COALESCE(p2.firstname,'')!='',CONCAT(' ',p2.firstname),''))) as p2name, p2.club as p2club,
             g.name as group_name
             FROM `match` m
             LEFT JOIN player p1 ON p1.id=m.player1_id
             LEFT JOIN player p2 ON p2.id=m.player2_id
             LEFT JOIN grp g ON g.id=m.group_id
             WHERE m.competition_id=? AND m.player1_id IS NOT NULL AND m.player2_id IS NOT NULL
               AND m.played=0
             ORDER BY $order",
            [$cid]
        );
    }

    // Optional: nur Spiele einer bestimmten Gruppe
    if ($gid) {
        $matches = array_values(array_filter($matches, fn($m) => (int)($m['group_id'] ?? 0) === $gid));
    }

    $team_size  = (int)($c['team_size'] ?? 0);
    $score_mode = $c['score_mode'] ?? 'match';
    // Karten füllen die Seite automatisch: dank page-break-inside:avoid passen so viele
    // Karten auf eine Seite wie Platz haben (kein fest erzwungener Umbruch).
    $card_min_h     = ($is_team && $team_size > 0) ? (18 + $team_size * 8) . 'mm' : (in_array($score_mode, ['sets', 'sets_grp']) ? '72mm' : '46mm');

    $html = pdf_css() . '<style>
        .card { border: 0.8pt solid #d1d5db; border-radius: 2mm; padding: 3mm 5mm;
                margin-bottom: 2mm; page-break-inside: avoid; min-height: ' . $card_min_h . '; }
        .card-hdr { background: #eff6ff; padding: 1mm 4mm; margin: -3mm -5mm 2mm;
                    border-radius: 2mm 2mm 0 0; font-weight: bold; font-size: 9pt; color: #1e40af; }
        .players { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        .players td { padding: 1mm 3mm; border: 0.3pt solid #e5e7eb; font-size: 10pt; }
        .players th { background: #f3f4f6; font-size: 8pt; padding: 0.8mm 3mm;
                      border: 0.3pt solid #e5e7eb; text-align: left; }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-write { height: 6mm; border-bottom: 0.6pt solid #374151; }
        .sig-label { font-size: 8pt; color: #4b5563; padding-top: 0.8mm; }
        .dtbl  { width:100%; border-collapse:collapse; margin-bottom:2mm; }
        .dtbl td { border:0.3pt solid #e5e7eb; }
        .dnum  { width:7mm; text-align:center; font-weight:bold; font-size:9pt; color:#6b7280; padding:1.2mm 1mm; }
        .dhdr-num { width:7mm; background:#eff6ff; }
        .dnm-l { width:40%; text-align:right; padding:1.2mm 3mm; }
        .dnm-r { width:40%; padding:1.2mm 3mm; }
        .dsc   { width:14%; text-align:center; font-size:9pt; padding:1.2mm 2mm; color:#374151; }
        .dhdr-l { width:40%; text-align:right; font-weight:bold; font-size:10pt;
                  padding:1.5mm 3mm; background:#eff6ff; }
        .dhdr-r { width:40%; font-weight:bold; font-size:10pt;
                  padding:1.5mm 3mm; background:#eff6ff; }
        .dhdr-sc { width:14%; text-align:center; font-size:11pt; padding:1.5mm 2mm;
                   background:#eff6ff; letter-spacing:1mm; }
        .wl  { display:block; border-bottom:0.6pt solid #374151; min-height:5.5mm; }
        .dlbl { font-size:7.5pt; color:#6b7280; }
    </style>';

    if (!$matches) {
        $html .= '<p style="color:#6b7280">Keine offenen Spiele gefunden.</p>';
    }
    // Blockgrößen für Platzierungsspiele (Modus groups_cross)
    $cross_S = [];
    foreach (db_fetchall("SELECT bracket, COUNT(*) n FROM `match` WHERE competition_id=? AND group_id IS NULL AND bracket LIKE 'C%' AND ko_round=1 GROUP BY bracket", [$cid]) as $row) {
        $cross_S[$row['bracket']] = 2 * (int)$row['n'];
    }
    $group_counters = [];
    foreach ($matches as $i => $m) {
        if ($m['group_name']) {
            $key = $m['group_name'];
            $group_counters[$key] = ($group_counters[$key] ?? 0) + 1;
            $label = e($m['group_name']);
            $game_nr = $group_counters[$key];
        } elseif (!empty($m['bracket']) && $m['bracket'][0] === 'C') {
            $S  = $cross_S[$m['bracket']] ?? 0;
            $ps = $S > 0 ? ($S >> ((int)$m['ko_round'] - 1)) : 2;
            $lo = (int)$m['place_lo']; $hi = $lo + $ps - 1;
            $label   = $ps <= 2 ? ('Platz ' . $lo . '/' . $hi) : ('Plätze ' . $lo . '–' . $hi);
            $game_nr = (int)$m['ko_position'] + 1;
        } elseif ($is_dko && isset($dko_labels[$m['id']])) {
            $label   = e($dko_labels[$m['id']]);
            $game_nr = $dko_seq[$m['id']] ?? ((int)$m['ko_position'] + 1);
        } else {
            $ko_round = (int)$m['ko_round'];
            $label = match($ko_round) {
                2  => 'Finale',
                3  => '3. Platz',
                4  => 'Halbfinale',
                8  => 'Viertelfinale',
                16 => 'Achtelfinale',
                default => 'KO Runde ' . $ko_round,
            };
            $game_nr = (int)$m['ko_position'] + 1;
        }
        // Spielrunden-Anzeige folgt der Bewerbsoption „Spielrunden anzeigen" (show_byes):
        // aktiv → „Runde N" (Gruppenspiele), sonst → „Spiel N". KO/Kreuz tragen die Runde im Label.
        $show_rounds = !empty($c['show_byes']);
        $round_card = ($show_rounds && !empty($m['round_no'])) ? ' &nbsp;·&nbsp; Runde ' . (int)$m['round_no'] : '';
        $court_card = !empty($m['court_no']) ? ' &nbsp;·&nbsp; ' . $court . ' ' . (int)$m['court_no'] : '';
        // Anwurf-Mannschaft (nur Team-Bewerb mit aktivierter Anwurf-Auslosung).
        $kick_card = '';
        if ($is_team && !empty($c['kickoff_enabled']) && !empty($m['kickoff_team_id'])) {
            $kt    = (int)$m['kickoff_team_id'];
            $kname = ($kt === (int)($m['team1_id'] ?? 0)) ? ($m['p1name'] ?? '')
                   : (($kt === (int)($m['team2_id'] ?? 0)) ? ($m['p2name'] ?? '') : '');
            if ($kname !== '') $kick_card = ' &nbsp;·&nbsp; Anwurf: ' . e($kname);
        }
        // „Spiel N" nur bei Gruppenspielen UND nur wenn keine Spielrunde angezeigt wird.
        $spiel_card = (!$show_rounds && !empty($m['group_name'])) ? ' &nbsp;·&nbsp; Spiel ' . $game_nr : '';
        $html .= '<div class="card">';
        $html .= '<div class="card-hdr">' . e($c['name']) . ' &nbsp;·&nbsp; ' . $label . $round_card . $spiel_card . $court_card . $kick_card . '</div>';

        if ($is_team && $team_size > 0) {
            // Team-Bewerb: Teamname-Zeile + Duel-Eintragszeilen
            $sc = $m['played'] ? $m['score1'] . ' : ' . $m['score2'] : '&nbsp;:&nbsp;';
            $html .= '<table class="dtbl">'
                   . '<tr>'
                   . '<td class="dhdr-num">&nbsp;</td>'
                   . '<td class="dhdr-l">' . e($m['p1name'] ?? '') . '</td>'
                   . '<td class="dhdr-sc">' . $sc . '</td>'
                   . '<td class="dhdr-r">' . e($m['p2name'] ?? '') . '</td>'
                   . '</tr>';
            for ($di = 1; $di <= $team_size; $di++) {
                $html .= '<tr>'
                       . '<td class="dnum" style="height:8mm">' . $di . '</td>'
                       . '<td class="dnm-l" style="height:8mm">&nbsp;</td>'
                       . '<td class="dsc" style="height:8mm">&nbsp;:&nbsp;</td>'
                       . '<td class="dnm-r" style="height:8mm">&nbsp;</td>'
                       . '</tr>';
            }
            $html .= '</table>';
        } else {
            // Einzel / Doppel
            $is_grp_match = !empty($m['group_name']);
            $use_sets_card = ($score_mode === 'sets') || ($score_mode === 'sets_grp' && $is_grp_match);
            $result_label  = $use_sets_card ? 'Sätze' : 'Ergebnis';
            $html .= '<table class="players">'
                . '<tr><th style="width:44%">Spieler 1</th><th style="width:12%;text-align:center">' . $result_label . '</th><th style="width:44%">Spieler 2</th></tr>'
                . '<tr>'
                . '<td>' . e($m['p1name'] ?? '') . ($m['p1club'] ? '<br><span style="font-size:8pt;color:#6b7280">' . e($m['p1club']) . '</span>' : '') . '</td>'
                . '<td style="text-align:center;font-size:14pt;letter-spacing:2mm">&nbsp;:&nbsp;</td>'
                . '<td>' . e($m['p2name'] ?? '') . ($m['p2club'] ? '<br><span style="font-size:8pt;color:#6b7280">' . e($m['p2club']) . '</span>' : '') . '</td>'
                . '</tr></table>';
            if ($use_sets_card) {
                $html .= '<table style="width:55%;margin:1mm auto;border-collapse:collapse;font-size:8.5pt">';
                for ($si = 1; $si <= 5; $si++) {
                    $html .= '<tr>'
                           . '<td style="text-align:right;color:#6b7280;padding:0.6mm 2mm;border:none;white-space:nowrap">Satz ' . $si . '</td>'
                           . '<td style="border:0.3pt solid #d1d5db;width:13mm;height:5.5mm"></td>'
                           . '<td style="text-align:center;border:none;padding:0.3mm 1.5mm">:</td>'
                           . '<td style="border:0.3pt solid #d1d5db;width:13mm;height:5.5mm"></td>'
                           . '</tr>';
                }
                $html .= '</table>';
            }
        }

        // Unterschriften
        $sig1 = $is_team ? e($m['p1name'] ?? 'Team 1') : 'Spieler 1';
        $sig2 = $is_team ? e($m['p2name'] ?? 'Team 2') : 'Spieler 2';
        $html .= '<table class="sig-table">'
            . '<tr>'
            . '<td style="width:50%;padding-right:6mm"><div class="sig-write"></div><div class="sig-label">Unterschrift ' . $sig1 . '</div></td>'
            . '<td style="width:50%;padding-left:6mm"><div class="sig-write"></div><div class="sig-label">Unterschrift ' . $sig2 . '</div></td>'
            . '</tr></table>';

        $html .= '</div>';
    }

    $pdf = mpdf(['margin_top' => 5, 'margin_bottom' => 5, 'margin_left' => 12, 'margin_right' => 12]);
    $pdf->WriteHTML($html);
    $pdf->Output($card_grp_name ? 'Match-Cards_' . _pdf_slug($card_grp_name) . '.pdf' : 'Match-Cards.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Nennungen-PDF (Admin) ─────────────────────────────────────────────────────

function generate_registrations_pdf(int $tid): void {
    $t = db_fetch("SELECT * FROM tournament WHERE id=?", [$tid]);
    if (!$t) { http_response_code(404); exit; }

    $regs = db_fetchall(
        "SELECT r.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as comps
         FROM registration r
         LEFT JOIN registration_competition rc ON rc.registration_id=r.id
         LEFT JOIN competition c ON c.id=rc.competition_id AND rc.status='confirmed'
         WHERE r.tournament_id=?
         GROUP BY r.id
         ORDER BY FIELD(r.status,'pending','confirmed','rejected'), r.lastname, r.firstname",
        [$tid]
    );

    $changes = db_fetchall(
        "SELECT rcr.id, rcr.request_type, rcr.status, rcr.created_at,
                r.lastname, r.firstname, r.email, r.club, r.pass_nr,
                GROUP_CONCAT(
                    CONCAT(IF(rcc.action='remove','− ','+ '), c.name)
                    ORDER BY rcc.action DESC, c.name SEPARATOR ', '
                ) as changes
         FROM registration_change_request rcr
         JOIN registration r ON r.id = rcr.registration_id
         LEFT JOIN registration_change_competition rcc ON rcc.change_request_id = rcr.id
         LEFT JOIN competition c ON c.id = rcc.competition_id
         WHERE r.tournament_id = ?
         GROUP BY rcr.id
         ORDER BY FIELD(rcr.status,'pending','confirmed','rejected'), r.lastname, r.firstname",
        [$tid]
    );

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">Nennungen: ' . e($t['name']) . '</h2>';
    $total     = count($regs);
    $confirmed = count(array_filter($regs, fn($r) => $r['status'] === 'confirmed'));
    $pending   = count(array_filter($regs, fn($r) => $r['status'] === 'pending'));
    $html .= '<div class="meta">Gesamt: ' . $total . ' &nbsp;|&nbsp; Bestätigt: ' . $confirmed . ' &nbsp;|&nbsp; Ausstehend: ' . $pending . '</div>';

    $html .= '<table><tr><th>Name</th><th>Verein</th><th>Pass-Nr.</th><th>E-Mail</th><th>Stärke</th><th>Bewerbe</th><th>Status</th><th>Datum</th></tr>';
    foreach ($regs as $i => $r) {
        $sc  = match($r['status']) { 'confirmed' => 'badge-ok', 'rejected' => 'badge-no', default => 'badge-pen' };
        $sl  = match($r['status']) { 'confirmed' => 'bestätigt', 'rejected' => 'abgelehnt', default => 'ausstehend' };
        $odd = $i % 2 === 1 ? ' class="odd"' : '';
        $html .= "<tr$odd>"
            . '<td>' . e($r['lastname'] . ' ' . $r['firstname']) . '</td>'
            . '<td>' . e($r['club'] ?? '') . '</td>'
            . '<td>' . e($r['pass_nr'] ?? '') . '</td>'
            . '<td style="font-size:7.5pt">' . e($r['email'] ?? '') . '</td>'
            . '<td style="text-align:right">' . ($r['skill'] ?: '') . '</td>'
            . '<td>' . e($r['comps'] ?? '—') . '</td>'
            . '<td class="' . $sc . '">' . $sl . '</td>'
            . '<td style="font-size:8pt;white-space:nowrap">' . e(substr($r['created_at'] ?? '', 0, 10)) . '</td>'
            . '</tr>';
    }
    $html .= '</table>';

    if ($changes) {
        $cpending = count(array_filter($changes, fn($c) => $c['status'] === 'pending'));
        $html .= '<h2>Bewerbsänderungen (' . count($changes) . ($cpending ? ', davon ' . $cpending . ' offen' : '') . ')</h2>';
        $html .= '<table><tr><th>Name</th><th>Verein</th><th>E-Mail</th><th>Typ</th><th>Änderungen / Bewerbe</th><th>Status</th><th>Datum</th></tr>';
        foreach ($changes as $i => $cr) {
            $sc  = match($cr['status']) { 'confirmed' => 'badge-ok', 'rejected' => 'badge-no', default => 'badge-pen' };
            $sl  = match($cr['status']) { 'confirmed' => 'bestätigt', 'rejected' => 'abgelehnt', default => 'ausstehend' };
            $tl  = $cr['request_type'] === 'withdraw' ? 'Rückzug' : 'Änderung';
            $odd = $i % 2 === 1 ? ' class="odd"' : '';
            $html .= "<tr$odd>"
                . '<td>' . e($cr['lastname'] . ' ' . $cr['firstname']) . '</td>'
                . '<td>' . e($cr['club'] ?? '') . '</td>'
                . '<td style="font-size:7.5pt">' . e($cr['email'] ?? '') . '</td>'
                . '<td>' . $tl . '</td>'
                . '<td>' . ($cr['request_type'] === 'withdraw' ? '<em style="color:#6b7280">gesamte Nennung</em>' : e($cr['changes'] ?? '—')) . '</td>'
                . '<td class="' . $sc . '">' . $sl . '</td>'
                . '<td style="font-size:8pt;white-space:nowrap">' . e(substr($cr['created_at'] ?? '', 0, 10)) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
    }

    $pdf = mpdf(['format' => 'A4-L']);
    $pdf->SetTitle('Nennungen: ' . $t['name']);
    $pdf->WriteHTML($html);
    $safe = preg_replace('/[^\w\-]/', '_', $t['name'] ?: 'Turnier');
    $pdf->Output('Nennungen_' . $safe . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Spielerregister-PDF ───────────────────────────────────────────────────────

function generate_players_registry_pdf(): void {
    $players = db_fetchall("SELECT * FROM player ORDER BY name, firstname");
    $skills  = [];
    foreach ($players as $pl) {
        $rows = db_fetchall(
            "SELECT sport, skill FROM player_skill WHERE player_id=? ORDER BY sport", [$pl['id']]
        );
        $skills[$pl['id']] = array_column($rows, 'skill', 'sport');
    }
    $sport_labels = ['tischtennis'=>'TT','tennis'=>'Ten','fussball'=>'Fuß','cornhole'=>'CH'];

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">Spielerregister</h2>';
    $html .= '<div class="meta">' . count($players) . ' Spieler</div>';
    $html .= '<table><tr><th>Nachname</th><th>Vorname</th><th>G</th><th>Verein</th>'
           . '<th>Pass-Nr.</th><th>E-Mail</th>';
    foreach ($sport_labels as $sk => $sl) $html .= "<th>$sl</th>";
    $html .= '</tr>';
    foreach ($players as $i => $pl) {
        $odd = $i % 2 === 1 ? ' class="odd"' : '';
        $html .= "<tr$odd>"
            . '<td>' . e($pl['name']) . '</td>'
            . '<td>' . e($pl['firstname'] ?? '') . '</td>'
            . '<td style="text-align:center">' . e($pl['gender'] ?? '') . '</td>'
            . '<td>' . e($pl['club'] ?? '') . '</td>'
            . '<td>' . e($pl['pass_nr'] ?? '') . '</td>'
            . '<td style="font-size:7.5pt">' . e($pl['email'] ?? '') . '</td>';
        foreach (array_keys($sport_labels) as $sk) {
            $html .= '<td style="text-align:right">' . ($skills[$pl['id']][$sk] ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    $pdf = mpdf(['format' => 'A4-L']);
    $pdf->SetTitle('Spielerregister');
    $pdf->WriteHTML($html);
    $pdf->Output('Spielerregister.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Spielerregister-CSV ───────────────────────────────────────────────────────

function generate_players_registry_csv(): void {
    $players = db_fetchall("SELECT * FROM player ORDER BY name, firstname");
    $sport_keys = ['tischtennis', 'tennis', 'fussball', 'cornhole'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Spielerregister.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM für Excel
    fputcsv($out, ['Nachname','Vorname','Geschlecht','Verein','Pass-Nr.','E-Mail',
                   'Stärke TT','Stärke Tennis','Stärke Fußball','Stärke Cornhole'], ';');
    foreach ($players as $pl) {
        $skills = db_fetchall(
            "SELECT sport, skill FROM player_skill WHERE player_id=?", [$pl['id']]
        );
        $sk_map = array_column($skills, 'skill', 'sport');
        fputcsv($out, csv_row([
            $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
            $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
            $sk_map['tischtennis'] ?? '', $sk_map['tennis'] ?? '',
            $sk_map['fussball'] ?? '',   $sk_map['cornhole'] ?? '',
        ]), ';');
    }
    fclose($out);
    exit;
}

// ── Doppelregister PDF ───────────────────────────────────────────────────────

function _doubles_with_sport_skills(): array {
    $doubles = db_fetchall(
        "SELECT d.id, d.name as dname,
         TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='', CONCAT(' ', p1.firstname), ''))) as p1full,
         p1.id as p1id, p1.name as p1name, p1.firstname as p1firstname, p1.club as p1club,
         TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='', CONCAT(' ', p2.firstname), ''))) as p2full,
         p2.id as p2id, p2.name as p2name, p2.firstname as p2firstname, p2.club as p2club
         FROM `double` d
         JOIN player p1 ON p1.id = d.player1_id
         JOIN player p2 ON p2.id = d.player2_id
         ORDER BY p1.name, p1.firstname",
        []
    );
    if (!$doubles) return [];

    $pids = array_unique(array_merge(array_column($doubles, 'p1id'), array_column($doubles, 'p2id')));
    $ph   = implode(',', array_fill(0, count($pids), '?'));
    $ps   = [];
    foreach (db_fetchall("SELECT player_id, sport, skill FROM player_skill WHERE player_id IN ($ph)", $pids) as $r) {
        $ps[$r['player_id']][$r['sport']] = (float)$r['skill'];
    }

    $sports = ['tischtennis', 'tennis', 'fussball', 'cornhole'];
    foreach ($doubles as &$d) {
        $s1 = $ps[$d['p1id']] ?? [];
        $s2 = $ps[$d['p2id']] ?? [];
        $d['skills'] = [];
        foreach ($sports as $sport) {
            $v1 = isset($s1[$sport]) ? (float)$s1[$sport] : ($sport === 'tennis' ? 10.0 : 0.0);
            $v2 = isset($s2[$sport]) ? (float)$s2[$sport] : ($sport === 'tennis' ? 10.0 : 0.0);
            $d['skills'][$sport] = round($v1 + $v2, 1);
        }
    }
    unset($d);
    return $doubles;
}

function generate_doubles_registry_pdf(): void {
    $doubles      = _doubles_with_sport_skills();
    $sport_labels = ['tischtennis'=>'TT', 'tennis'=>'Ten', 'fussball'=>'Fuß', 'cornhole'=>'CH'];

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">Doppelregister</h2>';
    $html .= '<div class="meta">' . count($doubles) . ' Doppel</div>';
    $html .= '<table><tr><th>#</th><th>Name</th><th>Spieler 1</th><th>Spieler 2</th><th>Verein</th>';
    foreach ($sport_labels as $sl) $html .= "<th style=\"text-align:right\">$sl</th>";
    $html .= '</tr>';
    foreach ($doubles as $i => $d) {
        $odd   = $i % 2 === 1 ? ' class="odd"' : '';
        $clubs = implode(' / ', array_unique(array_filter([$d['p1club'], $d['p2club']])));
        $html .= "<tr$odd>"
            . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
            . '<td>' . e($d['dname']) . '</td>'
            . '<td>' . e($d['p1full']) . '</td>'
            . '<td>' . e($d['p2full']) . '</td>'
            . '<td>' . e($clubs) . '</td>';
        foreach (array_keys($sport_labels) as $sport) {
            $v = $d['skills'][$sport] ?? 0;
            $html .= '<td style="text-align:right">' . ($v > 0 ? ($v == (int)$v ? (int)$v : $v) : '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    $pdf = mpdf(['format' => 'A4-L']);
    $pdf->SetTitle('Doppelregister');
    $pdf->WriteHTML($html);
    $pdf->Output('Doppelregister.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Doppelregister CSV ───────────────────────────────────────────────────────

function generate_doubles_registry_csv(): void {
    $doubles = _doubles_with_sport_skills();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Doppelregister.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Name', 'Spieler 1 Nachname', 'Spieler 1 Vorname', 'Spieler 1 Verein',
                   'Spieler 2 Nachname', 'Spieler 2 Vorname', 'Spieler 2 Verein',
                   'Stärke TT', 'Stärke Tennis', 'Stärke Fußball', 'Stärke Cornhole'], ';');
    $sports = ['tischtennis', 'tennis', 'fussball', 'cornhole'];
    foreach ($doubles as $d) {
        $row = [
            $d['dname'],
            $d['p1name'], $d['p1firstname'] ?? '', $d['p1club'] ?? '',
            $d['p2name'], $d['p2firstname'] ?? '', $d['p2club'] ?? '',
        ];
        foreach ($sports as $sport) {
            $v = $d['skills'][$sport] ?? 0;
            $row[] = $v > 0 ? $v : '';
        }
        fputcsv($out, csv_row($row), ';');
    }
    fclose($out);
    exit;
}

// ── Teamregister PDF ─────────────────────────────────────────────────────────

function generate_teams_registry_pdf(): void {
    $rows = db_fetchall(
        "SELECT t.id as tid, t.name as tname, t.skill as team_skill,
         p.name as pname, p.firstname, p.club
         FROM `team` t
         LEFT JOIN team_player tp ON tp.team_id = t.id
         LEFT JOIN player p ON p.id = tp.player_id
         ORDER BY t.name, p.name, p.firstname",
        []
    );

    $teams = [];
    foreach ($rows as $row) {
        $tid = $row['tid'];
        if (!isset($teams[$tid])) {
            $teams[$tid] = ['name' => $row['tname'], 'skill' => $row['team_skill'], 'players' => []];
        }
        if ($row['pname'] !== null) {
            $label = trim(($row['pname'] ?? '') . ' ' . ($row['firstname'] ?? ''));
            if ($row['club']) $label .= ' (' . $row['club'] . ')';
            $teams[$tid]['players'][] = $label;
        }
    }

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">Teamregister</h2>';
    $html .= '<div class="meta">' . count($teams) . ' Teams</div>';
    $html .= '<table><tr><th>#</th><th>Team</th><th>Spieler</th>'
           . '<th style="text-align:right">Stärke</th></tr>';
    $i = 0;
    foreach ($teams as $team) {
        $odd  = $i % 2 === 1 ? ' class="odd"' : '';
        $html .= "<tr$odd>"
            . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
            . '<td style="white-space:nowrap">' . e($team['name']) . '</td>'
            . '<td>' . implode('<br>', array_map('e', $team['players'])) . '</td>'
            . '<td style="text-align:right;white-space:nowrap">' . ($team['skill'] ?: '') . '</td>'
            . '</tr>';
        $i++;
    }
    $html .= '</table>';

    $pdf = mpdf();
    $pdf->SetTitle('Teamregister');
    $pdf->WriteHTML($html);
    $pdf->Output('Teamregister.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Teamregister CSV ─────────────────────────────────────────────────────────

function generate_teams_registry_csv(): void {
    $rows = db_fetchall(
        "SELECT t.name as tname, t.skill as team_skill,
         p.name as pname, p.firstname, p.club
         FROM `team` t
         LEFT JOIN team_player tp ON tp.team_id = t.id
         LEFT JOIN player p ON p.id = tp.player_id
         ORDER BY t.name, p.name, p.firstname",
        []
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Teamregister.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Team', 'Spielstärke Team', 'Nachname', 'Vorname', 'Verein'], ';');
    foreach ($rows as $row) {
        fputcsv($out, csv_row([
            $row['tname'], $row['team_skill'] ?? '',
            $row['pname'] ?? '', $row['firstname'] ?? '', $row['club'] ?? '',
        ]), ';');
    }
    fclose($out);
    exit;
}

// ── Turnier-Spielerliste PDF ──────────────────────────────────────────────────

function generate_tournament_players_pdf(int $tid): void {
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$tid]);
    if (!$t) { http_response_code(404); exit; }

    $players = db_fetchall(
        "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr,
         GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as competitions
         FROM player p
         JOIN (
             SELECT cp.player_id, cp.competition_id FROM competition_player cp
             UNION ALL
             SELECT d.player1_id, cd.competition_id FROM competition_double cd JOIN `double` d ON d.id = cd.double_id
             UNION ALL
             SELECT d.player2_id, cd.competition_id FROM competition_double cd JOIN `double` d ON d.id = cd.double_id
             UNION ALL
             SELECT tp.player_id, ct.competition_id FROM competition_team ct JOIN team_player tp ON tp.team_id = ct.team_id
         ) pc ON pc.player_id = p.id
         JOIN competition c ON c.id = pc.competition_id AND c.tournament_id = ?
         GROUP BY p.id ORDER BY p.name, p.firstname",
        [$tid]
    );

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">Spielerliste: ' . e($t['name']) . '</h2>';
    $html .= '<div class="meta">' . count($players) . ' Spieler</div>';
    $html .= '<table><tr><th>#</th><th>Nachname</th><th>Vorname</th><th>G</th>'
           . '<th>Verein</th><th>Pass-Nr.</th><th>Bewerbe</th></tr>';
    foreach ($players as $i => $pl) {
        $odd  = $i % 2 === 1 ? ' class="odd"' : '';
        $html .= "<tr$odd>"
            . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
            . '<td>' . e($pl['name']) . '</td>'
            . '<td>' . e($pl['firstname'] ?? '') . '</td>'
            . '<td style="text-align:center">' . e($pl['gender'] ?? '') . '</td>'
            . '<td>' . e($pl['club'] ?? '') . '</td>'
            . '<td>' . e($pl['pass_nr'] ?? '') . '</td>'
            . '<td style="font-size:8pt">' . e($pl['competitions'] ?? '') . '</td>'
            . '</tr>';
    }
    $html .= '</table>';

    $pdf  = mpdf();
    $safe = preg_replace('/[^\w\-]/', '_', $t['name'] ?: 'Turnier');
    $pdf->SetTitle('Spielerliste: ' . $t['name']);
    $pdf->WriteHTML($html);
    $pdf->Output('Spieler_' . $safe . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Turnier-Spielerliste CSV ──────────────────────────────────────────────────

function generate_tournament_players_csv(int $tid): void {
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$tid]);
    $players = db_fetchall(
        "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr, p.email,
         GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as competitions
         FROM player p
         JOIN (
             SELECT cp.player_id, cp.competition_id FROM competition_player cp
             UNION ALL
             SELECT d.player1_id, cd.competition_id FROM competition_double cd JOIN `double` d ON d.id = cd.double_id
             UNION ALL
             SELECT d.player2_id, cd.competition_id FROM competition_double cd JOIN `double` d ON d.id = cd.double_id
             UNION ALL
             SELECT tp.player_id, ct.competition_id FROM competition_team ct JOIN team_player tp ON tp.team_id = ct.team_id
         ) pc ON pc.player_id = p.id
         JOIN competition c ON c.id = pc.competition_id AND c.tournament_id = ?
         GROUP BY p.id ORDER BY p.name, p.firstname",
        [$tid]
    );
    $safe = preg_replace('/[^\w\-]/', '_', $t['name'] ?? 'Turnier');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Spieler_' . $safe . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Nachname','Vorname','Geschlecht','Verein','Pass-Nr.','E-Mail','Bewerbe'], ';');
    foreach ($players as $pl) {
        fputcsv($out, csv_row([
            $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
            $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
            $pl['competitions'] ?? '',
        ]), ';');
    }
    fclose($out);
    exit;
}

// ── Nennungen-CSV ─────────────────────────────────────────────────────────────

function generate_registrations_csv(int $tid): void {
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$tid]);
    $regs = db_fetchall(
        "SELECT r.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as comps
         FROM registration r
         LEFT JOIN registration_competition rc ON rc.registration_id=r.id
         LEFT JOIN competition c ON c.id=rc.competition_id AND rc.status='confirmed'
         WHERE r.tournament_id=?
         GROUP BY r.id ORDER BY r.lastname, r.firstname",
        [$tid]
    );
    $changes = db_fetchall(
        "SELECT rcr.request_type, rcr.status, rcr.created_at,
                r.lastname, r.firstname, r.email, r.club, r.pass_nr,
                GROUP_CONCAT(
                    CONCAT(IF(rcc.action='remove','- ','+ '), c.name)
                    ORDER BY rcc.action DESC, c.name SEPARATOR ', '
                ) as changes
         FROM registration_change_request rcr
         JOIN registration r ON r.id = rcr.registration_id
         LEFT JOIN registration_change_competition rcc ON rcc.change_request_id = rcr.id
         LEFT JOIN competition c ON c.id = rcc.competition_id
         WHERE r.tournament_id = ?
         GROUP BY rcr.id
         ORDER BY r.lastname, r.firstname",
        [$tid]
    );

    $safe = preg_replace('/[^\w\-]/', '_', $t['name'] ?? 'Turnier');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Nennungen_' . $safe . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Abschnitt 1: Nennungen
    fputcsv($out, ['--- NENNUNGEN ---'], ';');
    fputcsv($out, ['Nachname','Vorname','Verein','Geschlecht','Pass-Nr.','E-Mail',
                   'Spielstärke','Bewerbe','Status','Datum'], ';');
    foreach ($regs as $r) {
        $sl = match($r['status']) { 'confirmed'=>'bestätigt','rejected'=>'abgelehnt',default=>'ausstehend' };
        fputcsv($out, csv_row([
            $r['lastname'], $r['firstname'], $r['club'] ?? '', $r['gender'] ?? '',
            $r['pass_nr'] ?? '', $r['email'] ?? '', $r['skill'] ?? 0,
            $r['comps'] ?? '', $sl, substr($r['created_at'] ?? '', 0, 16),
        ]), ';');
    }

    // Abschnitt 2: Bewerbsänderungen
    if ($changes) {
        fputcsv($out, [], ';');
        fputcsv($out, ['--- BEWERBSÄNDERUNGEN ---'], ';');
        fputcsv($out, ['Nachname','Vorname','Verein','E-Mail','Pass-Nr.','Typ','Änderungen','Status','Datum'], ';');
        foreach ($changes as $cr) {
            $sl = match($cr['status']) { 'confirmed'=>'bestätigt','rejected'=>'abgelehnt',default=>'ausstehend' };
            $tl = $cr['request_type'] === 'withdraw' ? 'Rückzug' : 'Änderung';
            $ch = $cr['request_type'] === 'withdraw' ? 'gesamte Nennung' : ($cr['changes'] ?? '');
            fputcsv($out, csv_row([
                $cr['lastname'], $cr['firstname'], $cr['club'] ?? '', $cr['email'] ?? '',
                $cr['pass_nr'] ?? '', $tl, $ch, $sl, substr($cr['created_at'] ?? '', 0, 16),
            ]), ';');
        }
    }

    fclose($out);
    exit;
}

// ── Bewerbsspieler-PDF ────────────────────────────────────────────────────────

function generate_competition_players_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t    = db_fetch("SELECT name FROM tournament WHERE id=?", [$c['tournament_id']]);
    $html = pdf_css();
    $safe = preg_replace('/[^\w\-]/', '_', $c['name'] ?: 'Bewerb');

    if (!empty($c['is_team'])) {
        $rows = db_fetchall(
            "SELECT ct.skill as team_skill, t.id as tid, t.name as tname,
             p.name as pname, p.firstname, p.club
             FROM competition_team ct
             JOIN `team` t ON t.id = ct.team_id
             LEFT JOIN team_player tp ON tp.team_id = t.id
             LEFT JOIN player p ON p.id = tp.player_id
             WHERE ct.competition_id = ?
             ORDER BY t.name, p.name, p.firstname",
            [$cid]
        );
        $teams = [];
        foreach ($rows as $row) {
            $tid = $row['tid'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = ['name' => $row['tname'], 'skill' => $row['team_skill'], 'players' => []];
            }
            if ($row['pname'] !== null) {
                $label = trim(($row['pname'] ?? '') . ' ' . ($row['firstname'] ?? ''));
                if ($row['club']) $label .= ' (' . $row['club'] . ')';
                $teams[$tid]['players'][] = $label;
            }
        }
        $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Teamliste</h2>';
        if ($t) $html .= '<div class="meta">' . e($t['name']) . ' &nbsp;|&nbsp; ' . count($teams) . ' Teams</div>';
        $html .= '<table><tr><th>#</th><th>Team</th><th>Spieler</th><th style="text-align:right">Stärke</th></tr>';
        $i = 0;
        foreach ($teams as $team) {
            $odd  = $i % 2 === 1 ? ' class="odd"' : '';
            $html .= "<tr$odd>"
                . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
                . '<td style="white-space:nowrap">' . e($team['name']) . '</td>'
                . '<td>' . implode('<br>', array_map('e', $team['players'])) . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . ($team['skill'] ?: '') . '</td>'
                . '</tr>';
            $i++;
        }
        $html .= '</table>';

    } elseif (!empty($c['is_doubles'])) {
        $doubles = db_fetchall(
            "SELECT cd.skill,
             TRIM(CONCAT(p1.name, IF(COALESCE(p1.firstname,'')!='', CONCAT(' ', p1.firstname), ''))) as p1full,
             TRIM(CONCAT(p2.name, IF(COALESCE(p2.firstname,'')!='', CONCAT(' ', p2.firstname), ''))) as p2full,
             p1.club as p1club, p2.club as p2club
             FROM competition_double cd
             JOIN `double` d ON d.id = cd.double_id
             JOIN player p1 ON p1.id = d.player1_id
             JOIN player p2 ON p2.id = d.player2_id
             WHERE cd.competition_id = ?
             ORDER BY p1.name, p1.firstname",
            [$cid]
        );
        $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Doppelliste</h2>';
        if ($t) $html .= '<div class="meta">' . e($t['name']) . ' &nbsp;|&nbsp; ' . count($doubles) . ' Doppel</div>';
        $html .= '<table><tr><th>#</th><th>Spieler 1</th><th>Spieler 2</th><th>Verein</th><th style="text-align:right">Stärke</th></tr>';
        foreach ($doubles as $i => $d) {
            $odd   = $i % 2 === 1 ? ' class="odd"' : '';
            $clubs = implode(' / ', array_unique(array_filter([$d['p1club'], $d['p2club']])));
            $html .= "<tr$odd>"
                . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
                . '<td>' . e($d['p1full']) . '</td>'
                . '<td>' . e($d['p2full']) . '</td>'
                . '<td>' . e($clubs) . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . ($d['skill'] ?: '') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

    } else {
        $players = db_fetchall(
            "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr, cp.skill
             FROM competition_player cp
             JOIN player p ON p.id = cp.player_id
             WHERE cp.competition_id = ?
             ORDER BY p.name, p.firstname",
            [$cid]
        );
        $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Spielerliste</h2>';
        if ($t) $html .= '<div class="meta">' . e($t['name']) . ' &nbsp;|&nbsp; ' . count($players) . ' Spieler</div>';
        $html .= '<table><tr><th>#</th><th>Nachname</th><th>Vorname</th><th>G</th>'
               . '<th>Verein</th><th>Pass-Nr.</th><th style="text-align:right">Stärke</th></tr>';
        foreach ($players as $i => $pl) {
            $odd  = $i % 2 === 1 ? ' class="odd"' : '';
            $html .= "<tr$odd>"
                . '<td style="text-align:right;color:#6b7280">' . ($i + 1) . '</td>'
                . '<td>' . e($pl['name']) . '</td>'
                . '<td>' . e($pl['firstname'] ?? '') . '</td>'
                . '<td style="text-align:center">' . e($pl['gender'] ?? '') . '</td>'
                . '<td>' . e($pl['club'] ?? '') . '</td>'
                . '<td>' . e($pl['pass_nr'] ?? '') . '</td>'
                . '<td style="text-align:right">' . ($pl['skill'] ?: '') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
    }

    $pdf = mpdf();
    $pdf->SetTitle('Spielerliste: ' . $c['name']);
    $pdf->WriteHTML($html);
    $pdf->Output('Spieler_' . $safe . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Bewerbsspieler-CSV ────────────────────────────────────────────────────────

function generate_competition_players_csv(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }

    $safe = preg_replace('/[^\w\-]/', '_', $c['name'] ?: 'Bewerb');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Spieler_' . $safe . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if (!empty($c['is_team'])) {
        $rows = db_fetchall(
            "SELECT ct.skill as team_skill, t.name as tname,
             p.name as pname, p.firstname, p.club
             FROM competition_team ct
             JOIN `team` t ON t.id = ct.team_id
             LEFT JOIN team_player tp ON tp.team_id = t.id
             LEFT JOIN player p ON p.id = tp.player_id
             WHERE ct.competition_id = ?
             ORDER BY t.name, p.name, p.firstname",
            [$cid]
        );
        fputcsv($out, ['Team', 'Spielstärke Team', 'Nachname', 'Vorname', 'Verein'], ';');
        foreach ($rows as $row) {
            fputcsv($out, csv_row([
                $row['tname'], $row['team_skill'] ?? '',
                $row['pname'] ?? '', $row['firstname'] ?? '', $row['club'] ?? '',
            ]), ';');
        }

    } elseif (!empty($c['is_doubles'])) {
        $doubles = db_fetchall(
            "SELECT cd.skill,
             p1.name as p1name, p1.firstname as p1firstname, p1.club as p1club,
             p2.name as p2name, p2.firstname as p2firstname, p2.club as p2club
             FROM competition_double cd
             JOIN `double` d ON d.id = cd.double_id
             JOIN player p1 ON p1.id = d.player1_id
             JOIN player p2 ON p2.id = d.player2_id
             WHERE cd.competition_id = ?
             ORDER BY p1.name, p1.firstname",
            [$cid]
        );
        fputcsv($out, ['Spieler 1 Nachname','Spieler 1 Vorname','Spieler 1 Verein',
                       'Spieler 2 Nachname','Spieler 2 Vorname','Spieler 2 Verein','Spielstärke'], ';');
        foreach ($doubles as $d) {
            fputcsv($out, csv_row([
                $d['p1name'], $d['p1firstname'] ?? '', $d['p1club'] ?? '',
                $d['p2name'], $d['p2firstname'] ?? '', $d['p2club'] ?? '',
                $d['skill'] ?? '',
            ]), ';');
        }

    } else {
        $players = db_fetchall(
            "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr, p.email, cp.skill
             FROM competition_player cp
             JOIN player p ON p.id = cp.player_id
             WHERE cp.competition_id = ?
             ORDER BY p.name, p.firstname",
            [$cid]
        );
        fputcsv($out, ['Nachname','Vorname','Geschlecht','Verein','Pass-Nr.','E-Mail','Spielstärke'], ';');
        foreach ($players as $pl) {
            fputcsv($out, csv_row([
                $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
                $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
                $pl['skill'] ?? '',
            ]), ';');
        }
    }

    fclose($out);
    exit;
}
