<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$_dev = str_contains(APP_URL, 'localhost');
ini_set('display_errors', $_dev ? '1' : '0');
error_reporting($_dev ? E_ALL : E_ALL & ~E_DEPRECATED);
unset($_dev);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/tokens.php';

// Session sicher starten
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => str_starts_with(APP_URL, 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Security-Header
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data: blob:");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if (str_starts_with(APP_URL, 'https://')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// DB initialisieren
init_db();

// Letzten Besuch tracken (gedrosselt: max. 1 DB-Write pro 5 Minuten)
if (!empty($_SESSION['user_id'])) {
    $now = time();
    if (empty($_SESSION['_last_activity_db']) || $now - $_SESSION['_last_activity_db'] > 300) {
        db_execute("UPDATE user SET last_login=NOW() WHERE id=?", [$_SESSION['user_id']]);
        $_SESSION['_last_activity_db'] = $now;
    }
}

$uri = strtok($_SERVER['REQUEST_URI'], '?');
$uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');

// Uploads direkt ausliefern — MIME aus Extension-Whitelist, nicht vom Client
if (str_starts_with($uri, '/uploads/')) {
    $real = realpath(__DIR__ . $uri);
    $base = realpath(__DIR__ . '/uploads');
    $file = __DIR__ . $uri;
    if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(404); exit;
    }
    if (is_file($file)) {
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $safe_mimes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];
        $mime = $safe_mimes[$ext] ?? null;
        if (!$mime) { http_response_code(403); exit; }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

// Route-Matching
$method = $_SERVER['REQUEST_METHOD'];

// ── Route-Tabelle ─────────────────────────────────────────────────────────────
// Jede Route: [method, pattern, handler_file, action]
$routes = [
    // Auth
    ['GET|POST', '/login',            'auth',         'login'],
    ['GET',      '/logout',           'auth',         'logout'],
    ['GET|POST', '/register',         'auth',         'register'],
    ['GET',      '/confirm',          'auth',         'confirm'],
    ['GET|POST', '/forgot-password',  'auth',         'forgot_password'],
    ['GET|POST', '/reset-password',   'auth',         'reset_password'],

    // Tournaments
    ['GET',      '/',                           'tournament',  'index'],
    ['POST',     '/tournament/new',             'tournament',  'new_tournament'],
    ['GET',      '/tournament/{id}',            'tournament',  'show'],
    ['POST',     '/tournament/{id}/settings',   'tournament',  'settings'],
    ['POST',     '/tournament/{id}/delete',     'tournament',  'delete'],
    ['POST',     '/tournaments/reorder',                      'tournament',  'reorder'],
    ['POST',     '/tournament/{tid}/competitions/reorder',    'tournament',  'reorder_competitions'],
    ['GET',      '/tournament/{id}/ausschreibung', 'tournament', 'ausschreibung'],

    // Registrations (public + admin)
    ['GET|POST', '/tournament/{id}/register',                        'registration', 'register_form'],
    ['POST',     '/registration/{id}/confirm',                       'registration', 'confirm_all'],
    ['POST',     '/registration/{id}/reject',                        'registration', 'reject_all'],
    ['POST',     '/registration/{id}/comp/{cid}/confirm',            'registration', 'confirm_comp'],
    ['POST',     '/registration/{id}/comp/{cid}/reject',             'registration', 'reject_comp'],
    // Magic-Link: Verwaltungsseite anfordern + nutzen
    ['GET|POST', '/nennung/link',                                    'registration', 'request_link'],
    ['GET',      '/nennung/verwalten/{token}',                       'registration', 'manage_view'],
    ['POST',     '/nennung/verwalten/{token}/withdraw/{rid}',        'registration', 'manage_withdraw'],
    ['POST',     '/nennung/verwalten/{token}/change/{rid}',          'registration', 'manage_change'],

    // Admin: Change requests
    ['POST',     '/reg-change/{id}/confirm',                         'registration', 'change_confirm_all'],
    ['POST',     '/reg-change/{id}/reject',                          'registration', 'change_reject_all'],
    ['POST',     '/reg-change/{id}/comp/{cid}/confirm',              'registration', 'change_confirm_comp'],
    ['POST',     '/reg-change/{id}/comp/{cid}/reject',               'registration', 'change_reject_comp'],

    // Doubles
    ['GET',      '/tournament/{tid}/doppel',                  'double',      'list_doubles'],
    ['POST',     '/tournament/{tid}/doppel/neu',              'double',      'create_double'],
    ['POST',     '/tournament/{tid}/doppel/{did}/loeschen',   'double',      'delete_double'],

    // Competitions
    ['GET|POST', '/tournament/{tid}/competition/new',        'competition', 'new_competition'],
    ['GET',      '/competition/{id}',                        'competition', 'show'],
    ['GET|POST', '/competition/{id}/settings',               'competition', 'settings'],
    ['POST',     '/competition/{id}/delete',                 'competition', 'delete'],
    ['POST',     '/competition/{id}/player/add',             'competition', 'add_player'],
    ['POST',     '/competition/{id}/player/{pid}/remove',    'competition', 'remove_player'],
    ['POST',     '/competition/{id}/players/remove-all',    'competition', 'remove_all_players'],
    ['POST',     '/competition/{id}/player/{pid}/skill',     'competition', 'update_player_skill'],
    ['POST',     '/competition/{id}/double/add',             'competition', 'add_double'],
    ['POST',     '/competition/{id}/double/pair',            'competition', 'pair_double_from_reg'],
    ['POST',     '/competition/{id}/double/{did}/remove',    'competition', 'remove_double'],
    ['POST',     '/competition/{id}/doubles/remove-all',    'competition', 'remove_all_doubles'],
    ['POST',     '/competition/{id}/double/{did}/skill',     'competition', 'update_double_skill'],
    ['POST',     '/competition/{id}/team/add',               'competition', 'add_team'],
    ['POST',     '/competition/{id}/team/{tid}/remove',      'competition', 'remove_team'],
    ['POST',     '/competition/{id}/teams/remove-all',      'competition', 'remove_all_teams'],
    ['POST',     '/competition/{id}/team/{tid}/skill',       'competition', 'update_team_skill'],
    ['POST',     '/competition/{id}/draw/groups',            'competition', 'draw_groups'],
    ['POST',     '/competition/{id}/draw/ko',                'competition', 'draw_ko'],
    ['POST',     '/competition/{id}/draw/ko-direct',         'competition', 'draw_ko_direct'],
    ['POST',     '/competition/{id}/reset/groups',           'competition', 'reset_groups'],
    ['POST',     '/competition/{id}/reset/ko',               'competition', 'reset_ko'],
    ['POST',     '/competition/{id}/groups/reorder',         'competition', 'groups_reorder'],
    ['POST',     '/competition/{id}/courts',                 'competition', 'save_courts'],
    ['POST',     '/competition/{id}/ko/reorder',             'competition', 'ko_reorder'],
    ['POST',     '/competition/{id}/seedings/save',          'competition', 'seedings_save'],
    ['POST',     '/group/{gid}/tiebreak',                   'competition', 'save_group_tiebreak'],

    // Match results
    ['POST',     '/match/{id}/result',               'match_result', 'save'],
    ['POST',     '/match/{id}/result/clear',          'match_result', 'clear_result'],
    ['POST',     '/match/{id}/advance/{slot}',        'match_result', 'force_advance_ko'],
    ['POST',     '/match/{id}/duels',                'match_result', 'save_duels'],
    ['POST',     '/match/{id}/sets',                 'match_result', 'save_sets'],
    ['POST',     '/ko-match/{id}/result',             'match_result', 'save_ko'],
    ['POST',     '/competition/{id}/results/bulk',    'match_result', 'save_bulk'],

    // Players
    ['GET',      '/players',                        'player', 'index'],
    ['GET',      '/players/import/template',        'player', 'import_template'],
    ['GET|POST', '/players/import',                 'player', 'import_players'],
    ['GET',      '/players/doubles/import/template', 'player', 'import_doubles_template'],
    ['GET|POST', '/players/doubles/import',          'player', 'import_doubles'],
    ['GET',      '/players/teams/import/template',   'player', 'import_teams_template'],
    ['GET|POST', '/players/teams/import',            'player', 'import_teams'],
    ['GET|POST', '/player/new',                     'player', 'new_player'],
    ['GET|POST', '/player/{id}/edit',               'player', 'edit'],
    ['POST',     '/player/{id}/delete',             'player', 'delete'],
    ['POST',     '/player/{id}/toggle-active',      'player', 'toggle_active_player'],
    ['GET',      '/player/{id}/profile',            'player', 'player_profile_json'],
    ['GET',      '/player/{id}/sync/{source}',      'player', 'sync_external_skill'],
    ['POST',     '/players/double/new',             'player', 'create_double_global'],
    ['POST',     '/players/double/{did}/edit',      'player', 'edit_double_global'],
    ['POST',     '/players/double/{did}/delete',    'player', 'delete_double_global'],
    ['POST',     '/players/double/{did}/toggle-active', 'player', 'toggle_active_double'],
    ['POST',     '/players/team/new',                             'player', 'create_team_global'],
    ['POST',     '/players/team/{tid}/edit',                      'player', 'edit_team_global'],
    ['POST',     '/players/team/{tid}/delete',                    'player', 'delete_team_global'],
    ['POST',     '/players/team/{tid}/toggle-active',             'player', 'toggle_active_team'],
    ['POST',     '/players/team/{tid}/player/add',                'player', 'add_team_player'],
    ['POST',     '/players/team/{tid}/player/{pid}/remove',       'player', 'remove_team_player'],

    // PDFs & Exporte
    ['GET', '/tournament/{id}/aushang',           'pdf', 'aushang'],
    ['GET', '/competition/{id}/pdf/groups',       'pdf', 'groups'],
    ['GET', '/competition/{id}/pdf/groups/{gid}',      'pdf', 'groups'],
    ['GET', '/competition/{id}/pdf/ko',           'pdf', 'ko'],
    ['GET', '/competition/{id}/pdf/match-cards',  'pdf', 'match_cards'],
    ['GET', '/competition/{id}/pdf/match-cards/{gid}', 'pdf', 'match_cards'],
    ['GET', '/tournament/{id}/players/pdf',       'pdf', 'players_pdf'],
    ['GET', '/tournament/{id}/players/csv',       'pdf', 'players_csv'],
    ['GET', '/tournament/{id}/registrations/pdf', 'pdf', 'registrations_pdf'],
    ['GET', '/tournament/{id}/registrations/csv', 'pdf', 'registrations_csv'],
    ['GET', '/players/pdf',                       'pdf', 'players_registry_pdf'],
    ['GET', '/players/csv',                       'pdf', 'players_registry_csv'],
    ['GET', '/players/doubles/pdf',               'pdf', 'doubles_registry_pdf'],
    ['GET', '/players/doubles/csv',               'pdf', 'doubles_registry_csv'],
    ['GET', '/players/teams/pdf',                 'pdf', 'teams_registry_pdf'],
    ['GET', '/players/teams/csv',                 'pdf', 'teams_registry_csv'],
    ['GET', '/competition/{id}/players/pdf',      'pdf', 'competition_players_pdf'],
    ['GET', '/competition/{id}/players/csv',      'pdf', 'competition_players_csv'],

    // Hilfe
    ['GET',  '/hilfe',                    'help',  'help_page'],

    // Admin
    ['GET',  '/admin/users',              'admin', 'users'],
    ['POST', '/admin/user/{id}/role',     'admin', 'set_role'],
    ['POST', '/admin/user/{id}/delete',   'admin', 'delete_user'],
    ['GET',  '/admin/design',             'admin', 'design'],
    ['POST', '/admin/design',             'admin', 'save_design'],
];

// ── Routing ───────────────────────────────────────────────────────────────────
$params = [];
$matched = false;

foreach ($routes as [$route_methods, $pattern, $handler, $action]) {
    if (!in_array($method, explode('|', $route_methods), true)) continue;

    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $m)) {
        foreach ($m as $k => $v) {
            if (is_string($k)) $params[$k] = $v;
        }
        $matched = true;
        require_once __DIR__ . '/routes/' . $handler . '.php';
        $action($params);
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    render('error', ['message' => 'Seite nicht gefunden.', 'code' => 404]);
}
