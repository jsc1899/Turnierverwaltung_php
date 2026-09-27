<?php if (!defined('APP_BOOT') && PHP_SAPI !== 'cli') { http_response_code(404); exit; } // nur über index.php ?>
<?php
// Zugriffszähler für Turniere, Bewerbe und Galerie-Medien (Tabelle view_log).
// Gezählt wird je Besucher, Tag und Objekt einmal („Besucher-Tage“). Es werden keine IP-Adressen
// gespeichert: der Besucher wird nur über einen täglich wechselnden HMAC-Fingerabdruck aus IP,
// Browser-Kennung und Datum unterschieden (nicht umkehrbar, nicht tagesübergreifend verfolgbar).
// Nicht gezählt: angemeldete Admins/Editoren und erkennbare Bots.

const VIEW_TYPES = ['tournament', 'competition', 'gallery', 'gallery_tab'];   // gallery_tab: object_id = Turnier

function view_is_countable(?array $user, string $user_agent): bool {
    if ($user && in_array($user['role'] ?? '', ['admin', 'editor'], true)) return false;
    $ua = trim($user_agent);
    if ($ua === '') return false;
    return !preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|curl|wget|python|headless|monitor|scan/i', $ua);
}

function view_visitor_hash(string $ip, string $user_agent, string $day): string {
    return hash_hmac('sha256', $ip . '|' . $user_agent . '|' . $day, SECRET_KEY . '|views');
}

// Zählt einen Aufruf direkt (ohne Prüfung) — für Tests und view_record()
function view_record_for(string $type, int $id, string $visitor, string $day): void {
    if (!in_array($type, VIEW_TYPES, true) || $id <= 0) return;
    db_execute("INSERT IGNORE INTO view_log (object_type, object_id, day, visitor) VALUES (?, ?, ?, ?)",
               [$type, $id, $day, $visitor]);
}

// Aufruf des aktuellen Requests zählen (falls zählbar). Fehler brechen den Request nie ab.
function view_record(string $type, int $id): void {
    try {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!view_is_countable(current_user(), $ua)) return;
        $day = date('Y-m-d');
        view_record_for($type, $id, view_visitor_hash((string)($_SERVER['REMOTE_ADDR'] ?? ''), $ua, $day), $day);
    } catch (\Throwable $e) {
        error_log('view_record: ' . $e->getMessage());
    }
}

// [id => ['total' => Besucher-Tage gesamt, 'week' => in den letzten 7 Tagen]]
function view_counts(string $type, array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $out = [];
    foreach ($ids as $id) $out[$id] = ['total' => 0, 'week' => 0];
    if (!$ids) return $out;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_fetchall(
        "SELECT object_id, COUNT(*) AS total, SUM(day >= CURDATE() - INTERVAL 6 DAY) AS week
         FROM view_log WHERE object_type = ? AND object_id IN ($ph) GROUP BY object_id",
        array_merge([$type], $ids)
    );
    foreach ($rows as $r) {
        $out[(int)$r['object_id']] = ['total' => (int)$r['total'], 'week' => (int)$r['week']];
    }
    return $out;
}

function view_delete(string $type, array $ids): void {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !in_array($type, VIEW_TYPES, true)) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    db_execute("DELETE FROM view_log WHERE object_type = ? AND object_id IN ($ph)", array_merge([$type], $ids));
}

// Anzeige „👁 1.234 Aufrufe · 56 in 7 Tagen“ (nur für Bearbeiter aufrufen)
function view_badge_html(array $c): string {
    $fmt = fn(int $n) => number_format($n, 0, ',', '.');
    return '<span class="text-muted small text-nowrap" title="Besucher je Tag (ohne Admins/Editoren und Bots)">'
         . '<i class="bi bi-eye me-1"></i>' . $fmt($c['total']) . ($c['total'] === 1 ? ' Aufruf' : ' Aufrufe')
         . ' · ' . $fmt($c['week']) . ' in 7 Tagen</span>';
}
