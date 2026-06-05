<?php
require_once __DIR__ . '/../lib/standings.php';

// ── mPDF-Fabrik mit korrektem Temp-Verzeichnis ────────────────────────────────

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

function generate_groups_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t    = db_fetch("SELECT name FROM tournament WHERE id=?", [$c['tournament_id']]);
    $grps = db_fetchall("SELECT * FROM grp WHERE competition_id=? ORDER BY name", [$cid]);

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — Gruppenstand</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    foreach ($grps as $g) {
        $html .= '<h3>' . e($g['name']) . '</h3>';
        $st    = group_standings($g['id']);
        $html .= '<table><tr><th>#</th><th>Spieler</th><th>Verein</th>'
               . '<th>Sp</th><th>S</th><th>U</th><th>N</th>'
               . '<th>Tore</th><th>TD</th><th>Pkt</th></tr>';
        foreach ($st as $i => $pl) {
            $odd = $i % 2 === 1 ? ' class="odd"' : '';
            $html .= "<tr$odd><td>" . ($i+1)
                . '</td><td>' . e($pl['name'])
                . '</td><td>' . e($pl['club'])
                . '</td><td>' . $pl['played']
                . '</td><td>' . $pl['wins']
                . '</td><td>' . $pl['draws']
                . '</td><td>' . $pl['losses']
                . '</td><td>' . $pl['goals_for'] . ':' . $pl['goals_against']
                . '</td><td>' . ($pl['goal_diff'] >= 0 ? '+' : '') . $pl['goal_diff']
                . '</td><td><strong>' . $pl['points'] . '</strong></td></tr>';
        }
        $html .= '</table>';
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('Gruppenstand.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── KO-Bracket ────────────────────────────────────────────────────────────────

function generate_ko_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$c['tournament_id']]);

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
    $round_names = [
        2=>'Finale', 4=>'Halbfinale', 8=>'Viertelfinale',
        16=>'Achtelfinale', 32=>'Runde der 32', 64=>'Runde der 64', 3=>'Platz 3',
    ];
    $rounds = [];
    foreach ($matches as $m) { $rounds[(int)$m['ko_round']][] = $m; }
    krsort($rounds);

    $html  = pdf_css();
    $html .= '<h2 style="margin-top:0">' . e($c['name']) . ' — KO-Phase</h2>';
    if ($t) $html .= '<div class="meta">' . e($t['name']) . '</div>';

    foreach ($rounds as $r => $rmatches) {
        $html .= '<h3>' . ($round_names[$r] ?? "Runde $r") . '</h3>';
        $html .= '<table><tr><th>Spieler 1</th><th style="width:20mm;text-align:center">Ergebnis</th><th>Spieler 2</th></tr>';
        foreach ($rmatches as $i => $m) {
            $odd = $i % 2 === 1 ? ' class="odd"' : '';
            $s   = $m['played'] ? $m['score1'] . ' : ' . $m['score2'] : '— : —';
            $c1  = ($m['played'] && $m['score1'] > $m['score2']) ? ' class="winner"' : '';
            $c2  = ($m['played'] && $m['score2'] > $m['score1']) ? ' class="winner"' : '';
            $p1  = e($m['p1name'] ?: 'Freilos');
            $p2  = e($m['p2name'] ?: 'Freilos');
            $html .= "<tr$odd><td$c1>$p1</td><td style='text-align:center'>$s</td><td$c2>$p2</td></tr>";
        }
        $html .= '</table>';
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('KO-Phase.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Match-Cards (zum Ausdrucken) ──────────────────────────────────────────────

function generate_match_cards_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }

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
         ORDER BY g.name, m.match_order, m.id",
        [$cid]
    );

    $html = pdf_css() . '<style>
        .card { border: 0.8pt solid #d1d5db; border-radius: 2mm; padding: 4mm;
                margin-bottom: 5mm; page-break-inside: avoid; }
        .card-hdr { background: #eff6ff; padding: 2mm 4mm; margin: -4mm -4mm 3mm;
                    border-radius: 2mm 2mm 0 0; font-weight: bold; font-size: 9pt; color: #1e40af; }
        .players { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
        .players td { padding: 2mm 3mm; border: 0.3pt solid #e5e7eb; font-size: 10pt; }
        .players th { width: 25%; background: #f3f4f6; font-size: 8pt; padding: 1.5mm 3mm; border: 0.3pt solid #e5e7eb; }
        .sig { margin-top: 7mm; font-size: 8pt; color: #6b7280; }
        .sig-line { display: inline-block; width: 55mm; border-bottom: 0.5pt solid #9ca3af; margin: 0 2mm; }
    </style>';

    if (!$matches) {
        $html .= '<p style="color:#6b7280">Keine offenen Spiele gefunden.</p>';
    }
    foreach ($matches as $i => $m) {
        $label = $m['group_name'] ? e($m['group_name']) : 'KO';
        $html .= '<div class="card">';
        $html .= '<div class="card-hdr">' . e($c['name']) . ' &nbsp;·&nbsp; ' . $label . ' &nbsp;·&nbsp; Spiel ' . ($i+1) . '</div>';
        $html .= '<table class="players">';
        $html .= '<tr><th>Spieler 1</th><th>Ergebnis</th><th>Spieler 2</th><th>Sieger</th></tr>';
        $html .= '<tr>'
            . '<td>' . e($m['p1name'] ?? '') . ($m['p1club'] ? '<br><span style="font-size:8pt;color:#6b7280">' . e($m['p1club']) . '</span>' : '') . '</td>'
            . '<td style="text-align:center;font-size:14pt;letter-spacing:2mm">&nbsp; : &nbsp;</td>'
            . '<td>' . e($m['p2name'] ?? '') . ($m['p2club'] ? '<br><span style="font-size:8pt;color:#6b7280">' . e($m['p2club']) . '</span>' : '') . '</td>'
            . '<td></td>'
            . '</tr>';
        $html .= '</table>';
        $html .= '<div class="sig">Unterschrift: <span class="sig-line"></span> Spieler 1 &nbsp;&nbsp;&nbsp; <span class="sig-line"></span> Spieler 2</div>';
        $html .= '</div>';
    }

    $pdf = mpdf();
    $pdf->WriteHTML($html);
    $pdf->Output('Match-Cards.pdf', \Mpdf\Output\Destination::INLINE);
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
        fputcsv($out, [
            $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
            $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
            $sk_map['tischtennis'] ?? '', $sk_map['tennis'] ?? '',
            $sk_map['fussball'] ?? '',   $sk_map['cornhole'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Turnier-Spielerliste PDF ──────────────────────────────────────────────────

function generate_tournament_players_pdf(int $tid): void {
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$tid]);
    if (!$t) { http_response_code(404); exit; }

    $players = db_fetchall(
        "SELECT DISTINCT p.name, p.firstname, p.club, p.gender, p.pass_nr,
         GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as competitions
         FROM player p
         JOIN competition_player cp ON cp.player_id = p.id
         JOIN competition c ON c.id = cp.competition_id
         WHERE c.tournament_id = ?
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
        "SELECT DISTINCT p.name, p.firstname, p.club, p.gender, p.pass_nr, p.email,
         GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as competitions
         FROM player p
         JOIN competition_player cp ON cp.player_id=p.id
         JOIN competition c ON c.id=cp.competition_id
         WHERE c.tournament_id=?
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
        fputcsv($out, [
            $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
            $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
            $pl['competitions'] ?? '',
        ], ';');
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
        fputcsv($out, [
            $r['lastname'], $r['firstname'], $r['club'] ?? '', $r['gender'] ?? '',
            $r['pass_nr'] ?? '', $r['email'] ?? '', $r['skill'] ?? 0,
            $r['comps'] ?? '', $sl, substr($r['created_at'] ?? '', 0, 16),
        ], ';');
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
            fputcsv($out, [
                $cr['lastname'], $cr['firstname'], $cr['club'] ?? '', $cr['email'] ?? '',
                $cr['pass_nr'] ?? '', $tl, $ch, $sl, substr($cr['created_at'] ?? '', 0, 16),
            ], ';');
        }
    }

    fclose($out);
    exit;
}

// ── Bewerbsspieler-PDF ────────────────────────────────────────────────────────

function generate_competition_players_pdf(int $cid): void {
    $c = db_fetch("SELECT * FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }
    $t = db_fetch("SELECT name FROM tournament WHERE id=?", [$c['tournament_id']]);

    $players = db_fetchall(
        "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr, cp.skill
         FROM competition_player cp
         JOIN player p ON p.id = cp.player_id
         WHERE cp.competition_id = ?
         ORDER BY p.name, p.firstname",
        [$cid]
    );

    $html  = pdf_css();
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

    $pdf  = mpdf();
    $safe = preg_replace('/[^\w\-]/', '_', $c['name'] ?: 'Bewerb');
    $pdf->SetTitle('Spielerliste: ' . $c['name']);
    $pdf->WriteHTML($html);
    $pdf->Output('Spieler_' . $safe . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ── Bewerbsspieler-CSV ────────────────────────────────────────────────────────

function generate_competition_players_csv(int $cid): void {
    $c = db_fetch("SELECT name FROM competition WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit; }

    $players = db_fetchall(
        "SELECT p.name, p.firstname, p.club, p.gender, p.pass_nr, p.email, cp.skill
         FROM competition_player cp
         JOIN player p ON p.id = cp.player_id
         WHERE cp.competition_id = ?
         ORDER BY p.name, p.firstname",
        [$cid]
    );

    $safe = preg_replace('/[^\w\-]/', '_', $c['name'] ?: 'Bewerb');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Spieler_' . $safe . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Nachname','Vorname','Geschlecht','Verein','Pass-Nr.','E-Mail','Spielstärke'], ';');
    foreach ($players as $pl) {
        fputcsv($out, [
            $pl['name'], $pl['firstname'] ?? '', $pl['gender'] ?? '',
            $pl['club'] ?? '', $pl['pass_nr'] ?? '', $pl['email'] ?? '',
            $pl['skill'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}
