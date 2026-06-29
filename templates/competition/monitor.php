<?php
/**
 * Monitoransicht eines Bewerbs – eigenständiges Vollbild-HTML (kein _base.php).
 * Hell, große Schrift, Gruppentabellen im Raster, KO/Kreuz/Doppel-KO als Listen,
 * Endplatzierung oben. Automatisches langsames Auf-/Ab-Scrollen + Reload (60 s).
 *
 * Erwartete Variablen: $c, $t, $is_team, $is_doubles, $groups, $places, $comp_complete,
 * $ko_rounds, $third_place_match, $cross_blocks, $dko_wb, $dko_lb, $dko_gf, $cap, $lb_total.
 */
$phase_labels = ['setup'=>'Einrichtung','group'=>'Gruppenphase','ko'=>'KO-Phase','done'=>'Beendet'];
$phase_colors = ['setup'=>'secondary','group'=>'warning','ko'=>'info','done'=>'success'];

// Monitor-Einstellungen (Register „Monitor")
$mon_show_schedule = !empty($c['monitor_show_schedule']);
$mon_scroll_speed  = in_array($c['monitor_scroll_speed'] ?? 'medium', ['slow','medium','fast'], true) ? $c['monitor_scroll_speed'] : 'medium';
$mon_scroll_mode   = ($c['monitor_scroll_mode'] ?? 'smooth') === 'block' ? 'block' : 'smooth';
$mon_block_pause   = max(1, (int)($c['monitor_block_pause'] ?? 5));

// Markierung der Aufstiegsplätze (analog show.php)
$highlight_count = (int)($c['advance_count'] ?? 0);
if (($c['mode'] ?? '') === 'groups_cross') {
    $cc0 = explode(',', $c['cross_config'] ?? '');
    $highlight_count = (($cc0[0] ?? 'x') === 's') ? 1 : 2;
}

$winner = function(array $m): int {
    if (empty($m['played'])) return 0;
    if ((int)$m['score1'] > (int)$m['score2']) return 1;
    if ((int)$m['score2'] > (int)$m['score1']) return 2;
    return 0;
};
// Eine Begegnungszeile (Sieger fett, Score als Badge).
$matchLine = function(array $m, string $label = '') use ($winner): string {
    $w  = $winner($m);
    $n1 = trim((string)($m['p1name'] ?? '')) !== '' ? e($m['p1name']) : '<span class="text-muted">—</span>';
    $n2 = trim((string)($m['p2name'] ?? '')) !== '' ? e($m['p2name']) : '<span class="text-muted">—</span>';
    $sc = !empty($m['played']) ? ((int)$m['score1'] . ':' . (int)$m['score2']) : '–:–';
    $scCls = !empty($m['played']) ? 'bg-secondary' : 'bg-light text-muted border';
    $lbl = $label !== '' ? '<span class="mon-ko-lbl">' . e($label) . '</span>' : '';
    return '<div class="mon-ko-match">'
        . $lbl
        . '<span class="mon-ko-name text-end ' . ($w === 1 ? 'fw-bold' : '') . '">' . $n1 . '</span>'
        . '<span class="badge mon-ko-score ' . $scCls . '">' . $sc . '</span>'
        . '<span class="mon-ko-name ' . ($w === 2 ? 'fw-bold' : '') . '">' . $n2 . '</span>'
        . '</div>';
};

// Ein read-only KO-Kästchen für den Turnierbaum (zwei Teilnehmerzeilen, Sieger hervorgehoben).
$bracketBox = function(array $m, string $label = '', string $border = '#dee2e6') use ($winner): string {
    $w = $winner($m);
    $hdr = $label !== ''
        ? '<div style="font-size:.8rem;color:#9aa5b1;padding:2px 10px;border-bottom:1px solid #f3f5f7;background:#fafbfc">' . e($label) . '</div>'
        : '';
    $row = function($name, int $pid, $score, bool $isWin, bool $border_bottom): string {
        $nm = ($pid && trim((string)$name) !== '') ? e($name) : '<span class="text-muted fst-italic">—</span>';
        $bg = $isWin ? 'background:#d1e7dd;' : '';
        $fw = $isWin ? 'font-weight:700;' : '';
        $sc = $score !== null ? '<span class="ms-auto" style="flex-shrink:0;font-weight:700;font-size:1.05rem">' . (int)$score . '</span>' : '';
        return '<div class="d-flex align-items-center gap-2 px-2" style="min-height:42px;' . ($border_bottom ? 'border-bottom:1px solid #f0f0f0;' : '') . $bg . $fw . '">'
             . '<span class="flex-grow-1 text-truncate" style="min-width:0;font-size:1.02rem" title="' . e((string)$name) . '">' . $nm . '</span>'
             . $sc . '</div>';
    };
    $s1 = !empty($m['played']) ? (int)$m['score1'] : null;
    $s2 = !empty($m['played']) ? (int)$m['score2'] : null;
    return '<div class="ko-match" style="border:1px solid ' . $border . ';border-radius:8px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden">'
         . $hdr
         . $row($m['p1name'] ?? '', (int)($m['player1_id'] ?? 0), $s1, $w === 1, true)
         . $row($m['p2name'] ?? '', (int)($m['player2_id'] ?? 0), $s2, $w === 2, false)
         . '</div>';
};

// Spielplan-Begegnung mit farbigen Ergebnis-Kästchen (wie Webansicht):
// Sieger grün, Verlierer rot, Unentschieden beide blau.
$schedRow = function(array $m) use ($winner): string {
    $w  = $winner($m);
    $pl = !empty($m['played']);
    $n1 = trim((string)($m['p1name'] ?? '')) !== '' ? e($m['p1name']) : '<span class="text-muted">—</span>';
    $n2 = trim((string)($m['p2name'] ?? '')) !== '' ? e($m['p2name']) : '<span class="text-muted">—</span>';
    $c1 = $pl ? ($w === 1 ? ' score-win' : ($w === 2 ? ' score-loss' : ' score-draw')) : '';
    $c2 = $pl ? ($w === 2 ? ' score-win' : ($w === 1 ? ' score-loss' : ' score-draw')) : '';
    $s1 = $pl ? (int)$m['score1'] : '';
    $s2 = $pl ? (int)$m['score2'] : '';
    return '<div class="mon-sched-row">'
         . '<span class="nm nm1">' . $n1 . '</span>'
         . '<span class="sbox' . $c1 . '">' . $s1 . '</span>'
         . '<span class="sep">:</span>'
         . '<span class="sbox' . $c2 . '">' . $s2 . '</span>'
         . '<span class="nm nm2">' . $n2 . '</span>'
         . '</div>';
};

// Rendert einen Turnierbaum (Rundenüberschriften + Spalten + SVG-Overlay). Spalten sind
// dynamisch breit (flex:1) und füllen die verfügbare Breite, damit der Baum möglichst komplett
// sichtbar ist. $roundClass: 'ko-round' (KO/WB-Stil) oder 'lb-round' (Losers Bracket).
$renderTree = function(array $rounds, string $bracketId, string $svgId, string $roundClass, bool $trophyLast, int &$num, string $thirdHtml = '', bool $finalLabel = false) use ($bracketBox): string {
    $rounds = array_values($rounds);
    if (!$rounds) return '';
    $slot_h = 150;
    $first  = max(1, count($rounds[0]['matches'] ?? []));
    $h      = $first * $slot_h;
    $last   = count($rounds) - 1;
    $o  = '<div style="padding-bottom:.5rem">';
    $o .= '<div style="display:flex;width:100%">';
    foreach ($rounds as $ri => $rd) {
        $col = ($trophyLast && $ri === $last) ? '#856404' : '#6c757d';
        $tro = ($trophyLast && $ri === $last) ? '🏆 ' : '';
        $o  .= '<div style="flex:1 1 0;min-width:0;text-align:center;font-weight:700;font-size:1.05rem;padding:0 8px 6px;color:' . $col . '">' . $tro . e($rd['name']) . '</div>';
    }
    $o .= '</div>';
    $o .= '<div id="' . $bracketId . '" style="display:flex;position:relative;height:' . $h . 'px;width:100%">';
    foreach ($rounds as $ri => $rd) {
        $isLast3rd = ($ri === $last && $thirdHtml !== '');
        $isLastFin = ($finalLabel && $ri === $last);
        $cs = 'display:flex;flex-direction:column;justify-content:space-around;flex:1 1 0;min-width:0;height:100%;padding:0 8px' . ($isLast3rd ? ';position:relative' : '');
        $o .= '<div class="' . $roundClass . '" style="' . $cs . '">';
        if ($isLastFin) {
            $o .= '<div style="display:flex;flex-direction:column">';
            $o .= '<div style="text-align:center;font-weight:700;font-size:1.05rem;color:#856404;padding-bottom:6px">🏆 ' . e($rd['name']) . '</div>';
            foreach ($rd['matches'] as $m) { $num++; $o .= $bracketBox($m, 'Spiel ' . $num); }
            $o .= '</div>';
        } else {
            foreach ($rd['matches'] as $m) { $num++; $o .= $bracketBox($m, 'Spiel ' . $num); }
        }
        if ($isLast3rd) $o .= '<div class="mon-third-place" style="position:absolute;left:8px;right:8px">' . $thirdHtml . '</div>';
        $o .= '</div>';
    }
    $o .= '<svg id="' . $svgId . '" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible"></svg>';
    return $o . '</div></div>';
};

$has_ko = !empty($ko_rounds) || !empty($third_place_match) || !empty($cross_blocks)
       || !empty($dko_wb) || !empty($dko_lb) || !empty($dko_gf);
$teilnehmer_kopf = $is_team ? 'Mannschaft' : ($is_doubles ? 'Doppel' : 'Spieler');
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($c['name']) ?> — Monitor</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" type="image/x-icon" href="<?= url('static/favicon.ico') ?>">
  <style>
    html { font-size: 22px; }
    body { background:#f4f6f9; color:#1f2933; }
    .monitor-wrap { max-width: 1900px; margin: 0 auto; padding: 1rem 1.6rem 5rem; }
    .mon-head { display:flex; align-items:center; flex-wrap:wrap; gap:1rem; border-bottom:3px solid #dee2e6; padding-bottom:.6rem; margin-bottom:1.2rem; }
    .mon-head h1 { font-size:2.1rem; font-weight:800; margin:0; }
    .mon-head .sub { font-size:1.2rem; color:#6b7280; }
    .mon-clock { margin-left:auto; font-size:1.2rem; color:#6b7280; white-space:nowrap; }
    .mon-section-title { font-size:1.5rem; font-weight:700; margin:1.6rem 0 .8rem; display:flex; align-items:center; gap:.5rem; }

    /* Endplatzierung */
    .mon-podium { display:flex; flex-wrap:wrap; gap:1rem; }
    .mon-podium .item { text-align:center; padding:.8rem 1.4rem; border:2px solid #ffe08a; border-radius:.7rem; background:#fffdf3; min-width:160px; }
    .mon-podium .rank { font-size:2.2rem; line-height:1; }
    .mon-podium .nm { font-weight:700; font-size:1.15rem; margin-top:.3rem; }
    .mon-podium .club { color:#6b7280; font-size:.9rem; }
    .mon-rest { columns: 2; column-gap:2.5rem; margin-top:1rem; font-size:1.05rem; }
    .mon-rest div { break-inside:avoid; padding:.15rem 0; }
    .mon-rest .rk { display:inline-block; min-width:2.2rem; text-align:right; color:#6b7280; font-weight:600; margin-right:.5rem; }

    /* Gruppen-Raster */
    .grp-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(620px, 1fr)); gap:1.3rem; }
    .grp-card { border:1px solid #d8dee6; border-radius:.7rem; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.05); }
    .grp-card .hd { background:#eef2f7; padding:.5rem 1rem; font-size:1.3rem; font-weight:700; border-radius:.7rem .7rem 0 0; }
    table.mon-tbl { width:100%; border-collapse:collapse; font-size:1.05rem; margin:0; }
    table.mon-tbl th, table.mon-tbl td { padding:.45rem .55rem; border-bottom:1px solid #eceff3; text-align:center; }
    /* Zahlenspalten nie umbrechen; nur die Namensspalte darf umbrechen */
    table.mon-tbl th, table.mon-tbl td:not(.nm) { white-space:nowrap; }
    table.mon-tbl th { background:#f8fafc; font-size:.85rem; text-transform:uppercase; letter-spacing:.02em; color:#6b7280; }
    table.mon-tbl td.nm, table.mon-tbl th.nm { text-align:left; width:99%; overflow-wrap:anywhere; }
    table.mon-tbl td.pts { font-weight:800; }
    table.mon-tbl tr.adv td { background:#e8f7ec; }
    table.mon-tbl td.rk { font-weight:700; color:#374151; }
    .nm .club { color:#9aa5b1; font-size:.85rem; margin-left:.4rem; }

    /* KO / Kreuz / Doppel-KO */
    .mon-ko-cols { display:grid; grid-template-columns: repeat(auto-fit, minmax(560px, 1fr)); gap:1.3rem; }
    .mon-ko-round { border:1px solid #d8dee6; border-radius:.7rem; background:#fff; }
    .mon-ko-round .rhd { background:#eef2f7; padding:.45rem 1rem; font-weight:700; font-size:1.15rem; border-radius:.7rem .7rem 0 0; }
    .mon-ko-round .rbody { padding:.5rem .9rem; }
    .mon-ko-match { display:grid; grid-template-columns: 1fr auto 1fr; align-items:center; gap:.7rem; padding:.35rem 0; border-bottom:1px solid #f1f3f6; }
    .mon-ko-match:last-child { border-bottom:0; }
    .mon-ko-name { font-size:1.05rem; min-width:0; overflow-wrap:anywhere; }
    .mon-ko-score { font-size:1rem; letter-spacing:.05em; }
    .mon-ko-lbl { grid-column:1 / -1; font-size:.8rem; color:#9aa5b1; }
    .mon-ko-block-title { font-size:1.2rem; font-weight:700; margin:.4rem 0; }
    .mon-block { scroll-margin-top: 14px; }
    /* Spielplan je Gruppe (optional) */
    .mon-sched { padding:.45rem .8rem; border-top:1px solid #eef2f7; }
    .mon-sched-round { text-align:center; font-weight:700; font-size:.92rem; color:#5a6573; border-top:1px solid #e6eaef; margin-top:.4rem; padding-top:.3rem; }
    .mon-sched-round:first-child { border-top:0; margin-top:0; padding-top:0; }
    .mon-sched-pause { text-align:center; font-weight:700; font-size:.85rem; color:#856404; background:#fff3cd; border-radius:.3rem; padding:.2rem; margin:.35rem 0; }
    /* Begegnung mit farbigen Ergebnis-Kästchen (wie Webansicht) */
    .mon-sched-row { display:grid; grid-template-columns:1fr 2.4rem .6rem 2.4rem 1fr; align-items:center; gap:.4rem; padding:.25rem 0; }
    .mon-sched-row .nm { font-size:1.02rem; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mon-sched-row .nm1 { text-align:right; }
    .mon-sched-row .sep { text-align:center; color:#9aa5b1; }
    .mon-sched-row .sbox { display:inline-flex; align-items:center; justify-content:center; min-height:30px; font-weight:700;
                           border:1px solid #ced4da; border-radius:.25rem; background:#fff; }
    .mon-sched-row .score-win  { background:#d1e7dd; border-color:#a3cfbb; }
    .mon-sched-row .score-loss { background:#f8d7da; border-color:#e9a3ac; }
    .mon-sched-row .score-draw { background:#cfe2ff; border-color:#9ec5fe; }
  </style>
</head>
<body>
<div class="monitor-wrap">

  <div class="mon-head">
    <div>
      <h1><?= e($c['name']) ?></h1>
      <div class="sub"><?= $t ? e($t['name']) : '' ?></div>
    </div>
    <span class="badge fs-5 text-bg-<?= $phase_colors[$c['phase']] ?? 'secondary' ?>">
      <?= e(phase_label($c['phase'], $c['mode'] ?? null)) ?>
    </span>
    <span class="mon-clock"><i class="bi bi-clock"></i> Stand: <?= date('H:i') ?> Uhr</span>
  </div>

  <?php if (!empty($places) && !empty($comp_complete)): ?>
  <div class="mon-block">
  <div class="mon-section-title"><i class="bi bi-trophy-fill text-warning"></i>Endplatzierung</div>
  <div class="mon-podium">
    <?php foreach ($places as $pl): if ((int)$pl['rank'] > 4) continue; ?>
    <div class="item">
      <div class="rank"><?= match((int)$pl['rank']) { 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>(int)$pl['rank'].'.' } ?></div>
      <div class="nm"><?= e($pl['name']) ?></div>
      <?php if (!empty($pl['club'])): ?><div class="club"><?= e($pl['club']) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php $rest = array_filter($places, fn($p) => (int)$p['rank'] > 4); ?>
  <?php if ($rest): ?>
  <div class="mon-rest">
    <?php foreach ($rest as $pl): ?>
    <div><span class="rk"><?= (int)$pl['rank'] ?>.</span><?= e($pl['name']) ?><?php if (!empty($pl['club'])): ?> <span class="club" style="color:#9aa5b1"><?= e($pl['club']) ?></span><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($groups) && !$has_ko): // nur aktuelle Stage: Gruppen ausblenden, sobald Finalrunde läuft ?>
  <div class="mon-section-title"><i class="bi bi-table"></i>Gruppen</div>
  <div class="grp-grid">
    <?php foreach ($groups as $gi): $g = $gi['group']; $standings = $gi['standings']; ?>
    <div class="grp-card mon-block">
      <div class="hd"><?= e($g['name']) ?></div>
      <table class="mon-tbl">
        <thead>
          <tr>
            <th style="width:2.4rem">#</th>
            <th class="nm"><?= $teilnehmer_kopf ?></th>
            <th>Sp</th><th>S</th><th>U</th><th>N</th>
            <th>Tore</th><th>+/-</th><th>Pkt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($standings as $i => $pl): ?>
          <tr<?= $i < $highlight_count ? ' class="adv"' : '' ?>>
            <td class="rk"><?= $i + 1 ?></td>
            <td class="nm"><?= e($pl['name']) ?><?php if (!empty($pl['club'])): ?><span class="club"><?= e($pl['club']) ?></span><?php endif; ?></td>
            <td><?= (int)$pl['played'] ?></td>
            <td><?= (int)$pl['wins'] ?></td>
            <td><?= (int)$pl['draws'] ?></td>
            <td><?= (int)$pl['losses'] ?></td>
            <td><?= (int)$pl['goals_for'] ?>:<?= (int)$pl['goals_against'] ?></td>
            <td><?= ((int)$pl['goal_diff'] >= 0 ? '+' : '') . (int)$pl['goal_diff'] ?></td>
            <td class="pts"><?= (int)$pl['points'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($mon_show_schedule && !empty($gi['matches'])): ?>
      <div class="mon-sched">
        <?php
          $sched_prev = null;
          $grp_pause  = group_pause_info($c, $g);
          foreach ($gi['matches'] as $m):
            if (empty($m['player1_id']) || empty($m['player2_id'])) continue;
            $cur_round = (int)($m['round_no'] ?? 0);
            if ($cur_round !== $sched_prev) {
                // Pause zwischen den Runden (wie Webansicht)
                if ($grp_pause && $sched_prev !== null && $sched_prev <= $grp_pause['after_round'] && $cur_round > $grp_pause['after_round']) {
                    echo '<div class="mon-sched-pause">' . e($grp_pause['label']) . '</div>';
                }
                if ($cur_round > 0) {
                    $rt = group_round_time($c, $g, $cur_round);
                    echo '<div class="mon-sched-round">Runde ' . $cur_round . ($rt !== '' ? ' &middot; ' . $rt . ' Uhr' : '') . '</div>';
                }
                $sched_prev = $cur_round;
            }
            echo $schedRow($m);
          endforeach;
        ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($has_ko): ?>
  <div class="mon-section-title"><i class="bi bi-diagram-3"></i>Finalrunde</div>

  <?php if (!empty($cross_blocks)): // groups_cross ?>
  <?php foreach ($cross_blocks as $blk): ?>
  <div class="mon-block">
  <div class="mon-ko-block-title"><?= e($blk['label']) ?></div>
  <div class="mon-ko-cols">
    <?php foreach ($blk['rounds'] as $ri => $rd): ?>
    <div class="mon-ko-round">
      <div class="rhd">Runde <?= $ri + 1 ?></div>
      <div class="rbody">
        <?php foreach ($rd['matches'] as $m) { echo $matchLine($m, $m['place_label'] ?? ''); } ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  </div>
  <?php endforeach; ?>

  <?php elseif (!empty($dko_wb) || !empty($dko_gf)): // double_ko — Turnierbäume (Winners/Losers/Finale) ?>
  <?php $dko_num = 0; ?>
  <?php if (!empty($dko_wb)): ?>
  <div class="mon-block">
    <div class="mon-ko-block-title"><i class="bi bi-trophy-fill text-warning"></i> Winners Bracket</div>
    <?= $renderTree($dko_wb, 'mon-dko-wb-bracket', 'mon-dko-wb-svg', 'ko-round', true, $dko_num) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($dko_lb)): ?>
  <div class="mon-block">
    <div class="mon-ko-block-title"><i class="bi bi-arrow-down-circle text-danger"></i> Losers Bracket</div>
    <?= $renderTree($dko_lb, 'mon-dko-lb-bracket', 'mon-dko-lb-svg', 'lb-round', false, $dko_num) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($dko_gf)): ?>
  <div class="mon-block">
    <div class="mon-ko-block-title"><i class="bi bi-star-fill text-warning"></i> Großes Finale</div>
    <div style="max-width:520px"><?= $bracketBox($dko_gf, '', '#0d6efd') ?></div>
  </div>
  <?php endif; ?>

  <?php else: // groups_ko / ko_only — Turnierbaum mit Kästchen + Verbindungslinien ?>
  <?php $ko_num = 0; ?>
  <?php
  // Spiel um Platz 3 vorab bauen → wird direkt unter dem (zentrierten) Finale platziert.
  $monThirdHtml = '';
  if (!empty($third_place_match)) {
      $ko_total = 0;
      foreach ($ko_rounds as $__r) { $ko_total += count($__r['matches']); }
      ob_start(); ?>
      <div style="text-align:center;font-weight:700;font-size:1.05rem;color:#856404;padding-bottom:6px;margin-top:6px">🥉 <?= e($third_place_match['name']) ?></div>
      <?php foreach ($third_place_match['matches'] as $m) { echo $bracketBox($m, 'Spiel ' . ($ko_total + 1), '#ffc107'); }
      $monThirdHtml = ob_get_clean();
  }
  ?>
  <div class="mon-block">
  <?= $renderTree($ko_rounds, 'mon-ko-bracket', 'mon-ko-svg', 'ko-round', true, $ko_num, $monThirdHtml, true) ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<script>
// KO-Turnierbaum: Verbindungslinien zwischen den Kästchen zeichnen (wie Webansicht).
(function() {
  function bLine(svg, x1, y1, x2, y2) {
    var l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    l.setAttribute('x1', x1); l.setAttribute('y1', y1);
    l.setAttribute('x2', x2); l.setAttribute('y2', y2);
    l.setAttribute('stroke', '#adb5bd'); l.setAttribute('stroke-width', '2');
    svg.appendChild(l);
  }
  // Winners-/KO-Stil: zwei Kästchen → eines (1:2 Zusammenführung).
  function drawWB(bracket, svg) {
    if (!bracket || !svg) return;
    svg.innerHTML = '';
    var bRect = bracket.getBoundingClientRect();
    var rounds = bracket.querySelectorAll('.ko-round');
    for (var ri = 0; ri < rounds.length - 1; ri++) {
      var thisM = rounds[ri].querySelectorAll('.ko-match');
      var nextM = rounds[ri + 1].querySelectorAll('.ko-match');
      for (var ni = 0; ni < nextM.length; ni++) {
        var m1 = thisM[ni * 2], m2 = thisM[ni * 2 + 1], mn = nextM[ni];
        if (!m1 || !m2 || !mn) continue;
        var r1 = m1.getBoundingClientRect(), r2 = m2.getBoundingClientRect(), rn = mn.getBoundingClientRect();
        var x1 = r1.right - bRect.left, y1 = (r1.top + r1.bottom) / 2 - bRect.top;
        var x2 = r2.right - bRect.left, y2 = (r2.top + r2.bottom) / 2 - bRect.top;
        var xn = rn.left - bRect.left,  yn = (rn.top + rn.bottom) / 2 - bRect.top;
        var xm = (x1 + xn) / 2;
        bLine(svg, x1, y1, xm, y1);
        bLine(svg, x2, y2, xm, y2);
        bLine(svg, xm, y1, xm, y2);
        bLine(svg, xm, yn, xn, yn);
      }
    }
  }
  // Losers-Bracket: gerade Runden Minor→Major 1:1, ungerade Major→Minor 2:1.
  function drawLB(bracket, svg) {
    if (!bracket || !svg) return;
    svg.innerHTML = '';
    var bRect = bracket.getBoundingClientRect();
    var rounds = bracket.querySelectorAll('.lb-round');
    for (var ri = 0; ri < rounds.length - 1; ri++) {
      var cur = rounds[ri].querySelectorAll('.ko-match');
      var nxt = rounds[ri + 1].querySelectorAll('.ko-match');
      if (ri % 2 === 0) {
        for (var i = 0; i < cur.length; i++) {
          var ms = cur[i], mn = nxt[i];
          if (!ms || !mn) continue;
          var rs = ms.getBoundingClientRect(), rn = mn.getBoundingClientRect();
          var x1 = rs.right - bRect.left, y1 = (rs.top + rs.bottom) / 2 - bRect.top;
          var x2 = rn.left  - bRect.left, y2 = (rn.top + rn.bottom) / 2 - bRect.top;
          var xm = (x1 + x2) / 2;
          bLine(svg, x1, y1, xm, y1);
          bLine(svg, xm, y1, xm, y2);
          bLine(svg, xm, y2, x2, y2);
        }
      } else {
        for (var ni = 0; ni < nxt.length; ni++) {
          var m1 = cur[ni * 2], m2 = cur[ni * 2 + 1], mn = nxt[ni];
          if (!m1 || !m2 || !mn) continue;
          var r1 = m1.getBoundingClientRect(), r2 = m2.getBoundingClientRect(), rn = mn.getBoundingClientRect();
          var x1 = r1.right - bRect.left, y1 = (r1.top + r1.bottom) / 2 - bRect.top;
          var x2 = r2.right - bRect.left, y2 = (r2.top + r2.bottom) / 2 - bRect.top;
          var xn = rn.left  - bRect.left, yn = (rn.top + rn.bottom) / 2 - bRect.top;
          var xm = (x1 + xn) / 2;
          bLine(svg, x1, y1, xm, y1);
          bLine(svg, x2, y2, xm, y2);
          bLine(svg, xm, y1, xm, y2);
          bLine(svg, xm, yn, xn, yn);
        }
      }
    }
  }
  // Spiel um Platz 3 direkt unter dem (vertikal zentrierten) Finale platzieren.
  function positionThird() {
    var tp = document.querySelector('.mon-third-place'); if (!tp) return;
    var col = tp.closest('.ko-round'); if (!col) return;
    var fin = col.querySelector('.ko-match'); if (!fin) return;  // erstes .ko-match = Finale
    tp.style.top = (fin.offsetTop + fin.offsetHeight + 12) + 'px';
    var bracket = document.getElementById('mon-ko-bracket');
    if (bracket) {
      // Platz unterhalb reservieren (ohne Hoehe zu aendern → Finale bleibt zentriert)
      var overflow = (tp.offsetTop + tp.offsetHeight) - bracket.offsetHeight;
      bracket.style.marginBottom = (overflow > 0 ? overflow + 12 : 0) + 'px';
    }
  }
  function drawAll() {
    positionThird();
    drawWB(document.getElementById('mon-ko-bracket'),     document.getElementById('mon-ko-svg'));
    drawWB(document.getElementById('mon-dko-wb-bracket'),  document.getElementById('mon-dko-wb-svg'));
    drawLB(document.getElementById('mon-dko-lb-bracket'),  document.getElementById('mon-dko-lb-svg'));
  }
  window.addEventListener('load', drawAll);
  window.addEventListener('resize', drawAll);
  document.addEventListener('DOMContentLoaded', function(){ setTimeout(drawAll, 50); });
})();

// Auto-Scroll (gleichmäßig oder blockweise) + Auto-Reload nach abgeschlossenem Zyklus.
(function() {
  var cfg = {
    mode:  <?= json_encode($mon_scroll_mode) ?>,
    speed: <?= json_encode($mon_scroll_speed) ?>,
    pause: <?= (int)$mon_block_pause ?>
  };
  var SPEED_MAP = { slow: 14, medium: 28, fast: 52 }; // px/s
  var SPEED  = SPEED_MAP[cfg.speed] || 28;
  var PERIOD = 60000;     // ms: frühestens nach so langer Zeit neu laden (am Zyklusende)
  var EDGE_PAUSE = 2500;  // ms Pause an den Rändern (gleichmäßiger Modus)
  var loaded = Date.now();
  function maxScroll() { return Math.max(0, document.documentElement.scrollHeight - window.innerHeight); }
  function reloadNow() { location.reload(); }

  // Sicherheits-Watchdog: spätestens nach 10 min neu laden.
  setTimeout(reloadNow, 600000);

  if (maxScroll() <= 4) { setTimeout(reloadNow, PERIOD); return; }

  // ── Blockweise: zu jedem Abschnitt scrollen und dort verweilen ──────────────
  var blocks = Array.prototype.slice.call(document.querySelectorAll('.mon-block'));
  if (cfg.mode === 'block' && blocks.length) {
    var DWELL = Math.max(1, cfg.pause) * 1000;
    var idx = 0, phase = 'to', pos = window.scrollY || 0, last = performance.now(), dwellStart = 0;
    function targetFor(i) { return Math.min(maxScroll(), Math.max(0, blocks[i].offsetTop - 14)); }
    var target = targetFor(0);
    function step(now) {
      var dt = now - last; last = now;
      if (phase === 'to') {
        var dir = target > pos ? 1 : -1;
        pos += dir * SPEED * dt / 1000;
        if ((dir > 0 && pos >= target) || (dir < 0 && pos <= target)) {
          pos = target; phase = 'dwell'; dwellStart = now;
        }
        window.scrollTo(0, pos);
      } else if (phase === 'dwell') {
        if (now - dwellStart >= DWELL) {
          idx++;
          if (idx >= blocks.length) {
            if (Date.now() - loaded >= PERIOD) { reloadNow(); return; }
            idx = 0;
          }
          target = targetFor(idx); phase = 'to';
        }
      }
      requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
    return;
  }

  // ── Gleichmäßig: langsam runter, kurze Pause, wieder hoch ───────────────────
  var pos = 0, sphase = 'pauseTop', phaseStart = performance.now(), slast = performance.now();
  function sstep(now) {
    var dt = now - slast; slast = now;
    var max = maxScroll();
    if (sphase === 'pauseTop') {
      if (now - phaseStart >= EDGE_PAUSE) sphase = 'down';
    } else if (sphase === 'down') {
      pos += SPEED * dt / 1000;
      if (pos >= max) { pos = max; sphase = 'pauseBottom'; phaseStart = now; }
      window.scrollTo(0, pos);
    } else if (sphase === 'pauseBottom') {
      if (now - phaseStart >= EDGE_PAUSE) sphase = 'up';
    } else if (sphase === 'up') {
      pos -= SPEED * dt / 1000;
      if (pos <= 0) {
        pos = 0; window.scrollTo(0, 0);
        if (Date.now() - loaded >= PERIOD) { reloadNow(); return; }
        sphase = 'pauseTop'; phaseStart = now;
      } else {
        window.scrollTo(0, pos);
      }
    }
    requestAnimationFrame(sstep);
  }
  requestAnimationFrame(sstep);
})();
</script>
</body>
</html>
