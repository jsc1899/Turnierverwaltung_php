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
