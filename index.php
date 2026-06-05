<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
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

// DB initialisieren
init_db();

$uri = strtok($_SERVER['REQUEST_URI'], '?');
$uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');

// Uploads direkt ausliefern — MIME aus Extension-Whitelist, nicht vom Client
if (str_starts_with($uri, '/uploads/')) {
    $file = __DIR__ . $uri;
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

    // Competitions
    ['GET|POST', '/tournament/{tid}/competition/new',        'competition', 'new_competition'],
    ['GET',      '/competition/{id}',                        'competition', 'show'],
    ['GET|POST', '/competition/{id}/settings',               'competition', 'settings'],
    ['POST',     '/competition/{id}/delete',                 'competition', 'delete'],
    ['POST',     '/competition/{id}/player/add',             'competition', 'add_player'],
    ['POST',     '/competition/{id}/player/{pid}/remove',    'competition', 'remove_player'],
    ['POST',     '/competition/{id}/draw/groups',            'competition', 'draw_groups'],
    ['POST',     '/competition/{id}/draw/ko',                'competition', 'draw_ko'],
    ['POST',     '/competition/{id}/draw/ko-direct',         'competition', 'draw_ko_direct'],
    ['POST',     '/competition/{id}/reset/groups',           'competition', 'reset_groups'],
    ['POST',     '/competition/{id}/reset/ko',               'competition', 'reset_ko'],
    ['POST',     '/competition/{id}/groups/reorder',         'competition', 'groups_reorder'],
    ['POST',     '/competition/{id}/seedings/save',          'competition', 'seedings_save'],

    // Match results
    ['POST',     '/match/{id}/result',      'match_result', 'save'],
    ['POST',     '/ko-match/{id}/result',   'match_result', 'save_ko'],

    // Players
    ['GET',      '/players',               'player', 'index'],
    ['GET|POST', '/player/new',            'player', 'new_player'],
    ['GET|POST', '/player/{id}/edit',      'player', 'edit'],
    ['POST',     '/player/{id}/delete',    'player', 'delete'],

    // PDFs & Exporte
    ['GET', '/tournament/{id}/aushang',           'pdf', 'aushang'],
    ['GET', '/competition/{id}/pdf/groups',       'pdf', 'groups'],
    ['GET', '/competition/{id}/pdf/ko',           'pdf', 'ko'],
    ['GET', '/competition/{id}/pdf/match-cards',  'pdf', 'match_cards'],
    ['GET', '/tournament/{id}/players/pdf',       'pdf', 'players_pdf'],
    ['GET', '/tournament/{id}/players/csv',       'pdf', 'players_csv'],
    ['GET', '/tournament/{id}/registrations/pdf', 'pdf', 'registrations_pdf'],
    ['GET', '/tournament/{id}/registrations/csv', 'pdf', 'registrations_csv'],
    ['GET', '/players/pdf',                       'pdf', 'players_registry_pdf'],
    ['GET', '/players/csv',                       'pdf', 'players_registry_csv'],
    ['GET', '/competition/{id}/players/pdf',      'pdf', 'competition_players_pdf'],
    ['GET', '/competition/{id}/players/csv',      'pdf', 'competition_players_csv'],

    // Admin
    ['GET',  '/admin/users',              'admin', 'users'],
    ['POST', '/admin/user/{id}/role',     'admin', 'set_role'],
    ['POST', '/admin/user/{id}/delete',   'admin', 'delete_user'],
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
