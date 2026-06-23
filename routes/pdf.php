<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/pdf.php';

function aushang(array $p): void {
    $t = db_fetch("SELECT is_public FROM tournament WHERE id=?", [(int)$p['id']]);
    if (!$t) { http_response_code(404); exit; }
    if (!$t['is_public'] && !can_edit()) { http_response_code(403); exit; }
    generate_aushang_pdf((int)$p['id']);
}

function comp_aushang(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    generate_competition_aushang_pdf((int)$p['id']);
}

function groups(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    require_once __DIR__ . '/../lib/standings.php';
    generate_groups_pdf((int)$p['id'], isset($p['gid']) ? (int)$p['gid'] : null);
}

function ko(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    generate_ko_pdf((int)$p['id']);
}

function cross_pdf(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    require_once __DIR__ . '/../lib/standings.php';
    generate_cross_pdf((int)$p['id']);
}

function match_cards(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    generate_match_cards_pdf((int)$p['id'], isset($p['gid']) ? (int)$p['gid'] : null);
}

function team_strips(array $p): void {
    $c = db_fetch("SELECT t.is_public FROM competition c JOIN tournament t ON t.id=c.tournament_id WHERE c.id=?", [(int)$p['id']]);
    if (!$c) { http_response_code(404); exit; }
    if (!$c['is_public'] && !can_edit()) { http_response_code(403); exit; }
    require_once __DIR__ . '/../lib/standings.php';
    generate_team_strips_pdf((int)$p['id'], isset($p['gid']) ? (int)$p['gid'] : null);
}

function registrations_pdf(array $p): void {
    require_edit();
    generate_registrations_pdf((int)$p['id']);
}

function registrations_csv(array $p): void {
    require_edit();
    generate_registrations_csv((int)$p['id']);
}

function players_csv(array $p): void {
    require_edit();
    generate_tournament_players_csv((int)$p['id']);
}

function players_pdf(array $p): void {
    require_edit();
    generate_tournament_players_pdf((int)$p['id']);
}

function players_registry_pdf(array $p): void {
    require_edit();
    generate_players_registry_pdf();
}

function players_registry_csv(array $p): void {
    require_edit();
    generate_players_registry_csv();
}

function doubles_registry_pdf(array $p): void {
    require_edit();
    generate_doubles_registry_pdf();
}

function doubles_registry_csv(array $p): void {
    require_edit();
    generate_doubles_registry_csv();
}

function teams_registry_pdf(array $p): void {
    require_edit();
    generate_teams_registry_pdf();
}

function teams_registry_csv(array $p): void {
    require_edit();
    generate_teams_registry_csv();
}

function competition_players_pdf(array $p): void {
    require_edit();
    generate_competition_players_pdf((int)$p['id']);
}

function competition_players_csv(array $p): void {
    require_edit();
    generate_competition_players_csv((int)$p['id']);
}
