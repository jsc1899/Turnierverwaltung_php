<?php

// ── Flash Messages ─────────────────────────────────────────────────────────────

function flash(string $type, string $message, bool $html = false): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message, 'html' => $html];
}

function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

// ── CSRF ───────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF-Token ungültig.');
    }
}

// ── URL-Helper ─────────────────────────────────────────────────────────────────

function url(string $path = ''): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never {
    // Pfad hat kein eigenes Fragment → aktiven Tab aus POST-Param wiederherstellen
    if (!str_contains($path, '#')) {
        $tab = $_POST['_tab'] ?? '';
        if (preg_match('/^#tab-[\w-]+$/', $tab)) {
            $path .= $tab;
        }
    }
    header('Location: ' . url($path));
    exit;
}

function redirect_back(string $fallback = ''): never {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && str_starts_with($ref, APP_URL)) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

// ── Output-Escaping ────────────────────────────────────────────────────────────

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtdate(string $val): string {
    if (!$val) return '';
    $ts = strtotime($val);
    return $ts ? date('d.m.Y', $ts) : $val;
}

// ── Request-Helpers ────────────────────────────────────────────────────────────

function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get_param(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ── Audit-Protokoll ────────────────────────────────────────────────────────────
// Protokolliert privilegierte Aktionen (Funktionen, die Gästen nicht offenstehen).
// Aufgerufen aus den Auth-Gates (auth.php). $status: 'ok' = erfolgreiche Aktion (nur
// bei ändernden POST-Requests protokolliert), 'denied' = verweigerter Zugriff (immer
// protokolliert, auch GET/Gast — sicherheitsrelevant). Einmal pro Request; Fehler beim
// Schreiben dürfen den Request nie unterbrechen.
function audit_log(string $status): void {
    static $logged = false;
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($status === 'ok' && $method !== 'POST') return;
    if ($logged) return;
    $logged = true;
    try {
        $u = current_user();
        db_execute(
            "INSERT INTO audit_log (user_id, username, role, method, path, action, target, status, ip)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $u['id'] ?? null,
                $u['username'] ?? 'Gast',
                $u['role'] ?? '',
                substr($method, 0, 8),
                substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                substr((string)($GLOBALS['__audit_route'] ?? ''), 0, 64),
                audit_target(),
                $status === 'denied' ? 'denied' : 'ok',
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]
        );
    } catch (\Throwable $e) {
        // Logging darf den eigentlichen Request nicht stören
    }
}

// Löst das betroffene Objekt einer privilegierten Aktion in einen lesbaren Namen auf
// (z.B. Spielername bei Löschung, Editor-Name bei Zuordnung). Wird zur Gate-Zeit
// aufgerufen – das Objekt existiert dann noch (auch bei Löschaktionen). Liefert '' wenn
// kein sinnvolles Einzelobjekt bestimmbar ist. Fehler werden geschluckt.
function audit_target(): string {
    try {
        $route  = (string)($GLOBALS['__audit_route'] ?? '');
        $params = (array)($GLOBALS['__audit_params'] ?? []);
        [$handler, $action] = array_pad(explode('.', $route, 2), 2, '');
        $t = _audit_resolve_target($handler, $action, $params);
        return $t !== '' ? mb_substr($t, 0, 255) : '';
    } catch (\Throwable $e) {
        return '';
    }
}

// ── Namensauflösung für das Audit-Protokoll ────────────────────────────────────
function _audit_tname(int $id): string { $r = db_fetch("SELECT name FROM tournament WHERE id=?", [$id]); return ($r && $r['name'] !== '') ? $r['name'] : "Turnier #$id"; }
function _audit_cname(int $id): string { $r = db_fetch("SELECT name FROM competition WHERE id=?", [$id]); return ($r && $r['name'] !== '') ? $r['name'] : "Bewerb #$id"; }
function _audit_uname(int $id): string { $r = db_fetch("SELECT username FROM user WHERE id=?", [$id]); return ($r && $r['username'] !== '') ? $r['username'] : "Benutzer #$id"; }
function _audit_dname(int $id): string { $r = db_fetch("SELECT name FROM `double` WHERE id=?", [$id]); return ($r && $r['name'] !== '') ? $r['name'] : "Doppel #$id"; }
function _audit_teamname(int $id): string { $r = db_fetch("SELECT name FROM `team` WHERE id=?", [$id]); return ($r && $r['name'] !== '') ? $r['name'] : "Team #$id"; }
function _audit_pname(int $id): string {
    $r = db_fetch("SELECT firstname, name FROM player WHERE id=?", [$id]);
    if (!$r) return "Spieler #$id";
    return trim(($r['firstname'] !== '' ? $r['firstname'] . ' ' : '') . $r['name']);
}
function _audit_regname(int $id): string {
    $r = db_fetch("SELECT firstname, lastname FROM registration WHERE id=?", [$id]);
    return $r ? trim($r['firstname'] . ' ' . $r['lastname']) : "Nennung #$id";
}
function _audit_rcrname(int $id): string {
    $r = db_fetch("SELECT reg.firstname, reg.lastname FROM registration_change_request rcr
                   JOIN registration reg ON reg.id = rcr.registration_id WHERE rcr.id=?", [$id]);
    return $r ? trim($r['firstname'] . ' ' . $r['lastname']) : "Antrag #$id";
}
function _audit_matchdesc(int $mid): string {
    $m = db_fetch("SELECT * FROM `match` WHERE id=?", [$mid]);
    if (!$m) return "Spiel #$mid";
    $cn = _audit_cname((int)$m['competition_id']);
    if (!empty($m['team1_id']) || !empty($m['team2_id'])) {
        $a = $m['team1_id'] ? _audit_teamname((int)$m['team1_id']) : 'frei';
        $b = $m['team2_id'] ? _audit_teamname((int)$m['team2_id']) : 'frei';
    } elseif (!empty($m['double1_id']) || !empty($m['double2_id'])) {
        $a = $m['double1_id'] ? _audit_dname((int)$m['double1_id']) : 'frei';
        $b = $m['double2_id'] ? _audit_dname((int)$m['double2_id']) : 'frei';
    } else {
        $a = $m['player1_id'] ? _audit_pname((int)$m['player1_id']) : 'frei';
        $b = $m['player2_id'] ? _audit_pname((int)$m['player2_id']) : 'frei';
    }
    return "$cn: $a – $b";
}
// Liste von IDs zu Namen (cap Einträge, danach „+N")
function _audit_names(array $ids, callable $fn, int $cap = 4): string {
    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    if (!$ids) return '';
    $names = array_map($fn, array_slice($ids, 0, $cap));
    $extra = count($ids) - count($names);
    return implode(', ', $names) . ($extra > 0 ? " +$extra" : '');
}

function _audit_resolve_target(string $handler, string $action, array $params): string {
    $id  = (int)($params['id']  ?? 0);
    $tid = (int)($params['tid'] ?? 0);
    $pid = (int)($params['pid'] ?? 0);
    $did = (int)($params['did'] ?? 0);
    $uid = (int)($params['uid'] ?? 0);
    $gid = (int)($params['gid'] ?? 0);

    switch ($handler) {
        case 'tournament':
            return match ($action) {
                'new_tournament'       => trim((string)post('name')) ?: 'neues Turnier',
                'reorder'              => 'Turnier-Reihenfolge',
                'reorder_competitions' => _audit_tname($tid),
                'add_editor'           => _audit_uname((int)post('user_id')) . ' → ' . _audit_tname($id),
                'remove_editor'        => _audit_uname($uid) . ' → ' . _audit_tname($id),
                default                => _audit_tname($id),
            };

        case 'competition':
            return match ($action) {
                'new_competition'      => (trim((string)post('name')) ?: 'neuer Bewerb') . ' (' . _audit_tname($tid) . ')',
                'add_player'           => _audit_names($_POST['player_ids'] ?? [], '_audit_pname') . ' → ' . _audit_cname($id),
                'add_double'           => _audit_names($_POST['double_ids'] ?? [], '_audit_dname') . ' → ' . _audit_cname($id),
                'add_team'             => _audit_names($_POST['team_ids'] ?? [], '_audit_teamname') . ' → ' . _audit_cname($id),
                'remove_player', 'update_player_skill' => _audit_pname($pid) . ' (' . _audit_cname($id) . ')',
                'remove_double', 'update_double_skill' => _audit_dname($did) . ' (' . _audit_cname($id) . ')',
                'remove_team',   'update_team_skill'   => _audit_teamname($tid) . ' (' . _audit_cname($id) . ')',
                'save_group_tiebreak'  => _audit_cname((int)(db_fetch("SELECT competition_id FROM grp WHERE id=?", [$gid])['competition_id'] ?? 0)),
                default                => _audit_cname($id),
            };

        case 'match_result':
            if (in_array($action, ['save_bulk', 'save_duels_bulk', 'clear_phase_results'], true)) {
                return _audit_cname($id);
            }
            return _audit_matchdesc($id);

        case 'registration':
            if (str_starts_with($action, 'change_')) return _audit_rcrname($id);
            return _audit_regname($id);

        case 'player':
            return match ($action) {
                'new_player'           => trim(((string)post('firstname') !== '' ? post('firstname') . ' ' : '') . (string)post('name')) ?: 'neuer Spieler',
                'edit', 'delete', 'toggle_active_player' => _audit_pname($id),
                'edit_double_global', 'delete_double_global', 'toggle_active_double' => _audit_dname($did),
                'create_double_global' => 'neues Doppel',
                'edit_team_global', 'delete_team_global', 'toggle_active_team' => _audit_teamname($tid),
                'create_team_global'   => trim((string)post('name')) ?: 'neues Team',
                'add_team_player'      => _audit_pname((int)post('player_id')) . ' → ' . _audit_teamname($tid),
                'remove_team_player'   => _audit_pname($pid) . ' (' . _audit_teamname($tid) . ')',
                'import_players'       => 'Spieler-Import',
                'import_doubles'       => 'Doppel-Import',
                'import_teams'         => 'Team-Import',
                default                => '',
            };

        case 'admin':
            return match ($action) {
                'set_role'       => _audit_uname($id) . ' → Rolle ' . (string)post('role'),
                'toggle_active', 'resend_confirm', 'delete_user' => _audit_uname($id),
                'save_design'    => 'Design: ' . (string)post('theme'),
                'save_impressum' => 'Impressum',
                'clear_audit'    => 'Protokoll',
                default          => '',
            };

        case 'pdf':
            return in_array($action, ['aushang', 'players_pdf', 'players_csv', 'registrations_pdf', 'registrations_csv'], true)
                ? _audit_tname($id)
                : _audit_cname($id);
    }
    return '';
}

// Deutsche Bereichsbezeichnung zum Handler-Namen (für die Protokollanzeige)
function audit_area_label(string $handler): string {
    return [
        'tournament'   => 'Turnier',
        'competition'  => 'Bewerb',
        'match_result' => 'Ergebnis',
        'registration' => 'Nennung',
        'admin'        => 'Admin',
        'player'       => 'Spielerregister',
        'double'       => 'Doppel',
        'pdf'          => 'Export',
    ][$handler] ?? $handler;
}

// ── Rate-Limiting ──────────────────────────────────────────────────────────────

function rate_limit_check(string $action, int $max_attempts = 10, int $window_seconds = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $row = db_fetch(
        "SELECT attempts, window_start FROM rate_limit WHERE ip = ? AND action = ?",
        [$ip, $action]
    );
    if (!$row) {
        db_execute(
            "INSERT INTO rate_limit (ip, action, attempts, window_start) VALUES (?, ?, 1, NOW())",
            [$ip, $action]
        );
        return true;
    }
    $elapsed = time() - strtotime($row['window_start']);
    if ($elapsed > $window_seconds) {
        db_execute(
            "UPDATE rate_limit SET attempts = 1, window_start = NOW() WHERE ip = ? AND action = ?",
            [$ip, $action]
        );
        return true;
    }
    if ($row['attempts'] >= $max_attempts) {
        return false;
    }
    db_execute(
        "UPDATE rate_limit SET attempts = attempts + 1 WHERE ip = ? AND action = ?",
        [$ip, $action]
    );
    return true;
}

// ── Datei-Upload ───────────────────────────────────────────────────────────────

function upload_file(string $field, array $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png']): ?string {
    if (empty($_FILES[$field]['tmp_name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5 MB

    // Realen MIME-Typ per finfo prüfen, nicht den vom Client gemeldeten
    static $mime_whitelist = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $finfo     = new \finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = array_intersect_key($mime_whitelist, array_flip($allowed_exts));
    if (!in_array($real_mime, $allowed_mimes, true)) return null;

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
    return $filename;
}

// ── Spielplatz-Bezeichnung je Sportart ─────────────────────────────────────────
// Tischtennis=Tisch, Tennis=Tennisplatz, Fußball=Spielfeld, Cornhole=Bahn, sonst Platz.

/**
 * Anzeigename der Bewerbs-Phase. Bei Modus 'groups_cross' heißt die Endrunde
 * „Kreuzspiele" statt „KO-Runde".
 */
function phase_label(string $phase, ?string $mode = null): string {
    switch ($phase) {
        case 'setup': return 'Einrichtung';
        case 'group': return 'Gruppenphase';
        case 'ko':    return $mode === 'groups_cross' ? 'Kreuzspiele' : 'KO-Runde';
        case 'done':  return 'Beendet';
        default:      return $phase;
    }
}

function court_label(string $sport = '', bool $plural = false): string {
    $map = [
        'tischtennis' => ['Tisch',       'Tische'],
        'tennis'      => ['Tennisplatz', 'Tennisplätze'],
        'fussball'    => ['Spielfeld',   'Spielfelder'],
        'cornhole'    => ['Bahn',        'Bahnen'],
    ];
    $pair = $map[$sport] ?? ['Platz', 'Plätze'];
    return $plural ? $pair[1] : $pair[0];
}

// Kurzform (1–2 Zeichen) für schmale Spaltenköpfe (z.B. Teampläne-PDF).
function court_abbr(string $sport = ''): string {
    return ['tischtennis' => 'Ti', 'tennis' => 'Te', 'fussball' => 'Fe', 'cornhole' => 'B'][$sport] ?? 'Pl';
}

// ── Zeitplan (Bewerbsoption schedule_*) ────────────────────────────────────────
// 'HH:MM' → Minuten ab Mitternacht, oder null bei ungültigem Format.
function _hhmm_to_min(string $hhmm): ?int {
    return preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $mm) ? ((int)$mm[1] * 60 + (int)$mm[2]) : null;
}
// Minuten ab Mitternacht → 'HH:MM' (auf den Tag normalisiert).
function _min_to_hhmm(int $t): string {
    $t = (($t % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
}

// Startminute einer Runde (ohne Pause): Startzeit + (Runde-1)·Spieldauer; null wenn unkonfiguriert.
function schedule_round_minutes(string $start, int $duration, int $round): ?int {
    $st = _hhmm_to_min($start);
    if ($round < 1 || $duration <= 0 || $st === null) return null;
    return $st + ($round - 1) * $duration;
}

// Startzeit einer Runde (ohne Gruppen-Pause). '' wenn unkonfiguriert.
function schedule_round_time(string $start, int $duration, int $round): string {
    $m = schedule_round_minutes($start, $duration, $round);
    return $m === null ? '' : _min_to_hhmm($m);
}

// ── Gruppen-Pause (grp.pause_start / grp.pause_duration) ───────────────────────
// Runde, NACH der die Pause eingeplant wird (an der ersten Rundengrenze ≥ Pausenzeit).
// 0 = keine (gültige) Pause. $c = competition, $g = grp-Zeile.
function group_pause_after_round(array $c, array $g): int {
    if (empty($c['schedule_enabled']) || (int)($g['pause_duration'] ?? 0) <= 0) return 0;
    $st  = _hhmm_to_min((string)($c['schedule_start'] ?? ''));
    $ps  = _hhmm_to_min((string)($g['pause_start'] ?? ''));
    $dur = (int)($c['schedule_duration'] ?? 0);
    if ($st === null || $ps === null || $dur <= 0) return 0;
    $p = (int)ceil(($ps - $st) / $dur);   // erste Rundengrenze ≥ Pausenzeit → nach Runde p
    return $p >= 1 ? $p : 0;
}

// Pausenfenster einer Gruppe: ['after_round'=>p, 'start'=>'HH:MM', 'end'=>'HH:MM'] oder null.
function group_pause_window(array $c, array $g): ?array {
    $p = group_pause_after_round($c, $g);
    if ($p < 1) return null;
    $startMin = schedule_round_minutes((string)$c['schedule_start'], (int)$c['schedule_duration'], $p + 1); // = Ende Runde p
    if ($startMin === null) return null;
    return ['after_round' => $p, 'start' => _min_to_hhmm($startMin), 'end' => _min_to_hhmm($startMin + (int)$g['pause_duration'])];
}

// Einheitliche Pausen-Info einer Gruppe – für Anzeige in Spielplan/Übersicht/PDFs.
// Liefert ['after_round'=>N, 'label'=>'…'] oder null. Zwei Quellen, sich gegenseitig ausschließend:
//  - aktiver Zeitplan → zeitbasierte Pause (group_pause_window), Label mit Uhrzeiten
//  - ohne Zeitplan    → rundenbasierte Pause (grp.pause_after_round), Label „Pause nach Runde N"
function group_pause_info(array $c, array $g): ?array {
    $pw = group_pause_window($c, $g);
    if ($pw) {
        return ['after_round' => $pw['after_round'], 'label' => 'Pause · ' . $pw['start'] . ' – ' . $pw['end'] . ' Uhr'];
    }
    if (empty($c['schedule_enabled'])) {
        $ra = (int)($g['pause_after_round'] ?? 0);
        if ($ra >= 1) {
            $lbl = trim((string)($g['pause_label'] ?? ''));
            return ['after_round' => $ra, 'label' => $lbl !== '' ? $lbl : 'Pause'];
        }
    }
    return null;
}

// Startzeit einer Runde inkl. Gruppen-Pause (Runden nach der Pause sind verschoben). '' wenn aus.
function group_round_time(array $c, array $g, int $round): string {
    $min = schedule_round_minutes((string)($c['schedule_start'] ?? ''), (int)($c['schedule_duration'] ?? 0), $round);
    if (empty($c['schedule_enabled']) || $min === null) return '';
    $p = group_pause_after_round($c, $g);
    if ($p >= 1 && $round > $p) $min += (int)($g['pause_duration'] ?? 0);
    return _min_to_hhmm($min);
}

// Startzeit einer (Gruppen-)Begegnung, sofern der Zeitplan aktiv ist; sonst ''. Nutzt — falls auf
// $m vorhanden — die Pausenspalten (pause_start/pause_duration) der zugehörigen Gruppe.
function match_schedule_time(array $c, array $m): string {
    if (empty($c['schedule_enabled'])) return '';
    $round = (int)($m['round_no'] ?? 0);
    if ($round < 1) return '';
    $g = ['pause_start' => $m['pause_start'] ?? '', 'pause_duration' => $m['pause_duration'] ?? 0];
    return group_round_time($c, $g, $round);
}

// ── Spielstärke-Helper ─────────────────────────────────────────────────────────

function player_sport_skill(int $pid, string $sport): float {
    static $defaults = ['tennis' => 10.0];
    if ($sport) {
        $row = db_fetch("SELECT skill FROM player_skill WHERE player_id=? AND sport=?", [$pid, $sport]);
        if ($row) return (float)$row['skill'];
        return $defaults[$sport] ?? 0.0;
    }
    $row = db_fetch("SELECT skill FROM player WHERE id=?", [$pid]);
    return $row ? (float)$row['skill'] : 0.0;
}

// ── Doppel-Helper ──────────────────────────────────────────────────────────────

function double_name(int $did): string {
    $d = db_fetch(
        "SELECT d.name,
         TRIM(CONCAT(COALESCE(p1.firstname,''), IF(COALESCE(p1.firstname,'') != '', ' ', ''), p1.name)) as p1name,
         TRIM(CONCAT(COALESCE(p2.firstname,''), IF(COALESCE(p2.firstname,'') != '', ' ', ''), p2.name)) as p2name
         FROM `double` d
         JOIN player p1 ON p1.id = d.player1_id
         JOIN player p2 ON p2.id = d.player2_id
         WHERE d.id = ?",
        [$did]
    );
    if (!$d) return '?';
    return $d['name'] ?: ($d['p1name'] . ' / ' . $d['p2name']);
}

// ── Turnier-Sperren ────────────────────────────────────────────────────────────

function require_tournament_open(int $tid): void {
    $t = db_fetch("SELECT is_done FROM tournament WHERE id = ?", [$tid]);
    if ($t && (int)$t['is_done'] === 1) {
        flash('danger', 'Dieses Turnier ist beendet – Änderungen sind nicht mehr möglich.');
        redirect('tournament/' . $tid);
    }
}

function require_competition_open(int $cid): void {
    $row = db_fetch(
        "SELECT t.is_done, c.phase FROM competition c JOIN tournament t ON t.id = c.tournament_id WHERE c.id = ?",
        [$cid]
    );
    if ($row && ((int)$row['is_done'] === 1 || $row['phase'] === 'done')) {
        flash('danger', 'Dieser Bewerb ist beendet – Änderungen sind nicht mehr möglich.');
        redirect('competition/' . $cid);
    }
}

// ── Git-Version ────────────────────────────────────────────────────────────────

function get_git_version(): string {
    $f = __DIR__ . '/version.txt';
    if (!file_exists($f)) return '';
    return trim((string)file_get_contents($f));
}

// ── Render ─────────────────────────────────────────────────────────────────────

function render(string $template, array $vars = []): void {
    extract($vars);
    $flashes = get_flashes();
    require __DIR__ . '/templates/' . $template . '.php';
}
