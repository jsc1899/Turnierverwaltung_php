# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Turnierverwaltung PHP — Tournament management web application built with PHP 8.3 + MariaDB/MySQL. German-language UI. PHP port of a parallel Python/Flask project (`C:\Users\juerg\claude\Turnierverwaltung`).

## Running locally

**Start MariaDB** (no service, must be started manually each time):
```powershell
Start-Process "C:\Program Files\MariaDB 12.3\bin\mysqld.exe" -WindowStyle Hidden
```

**Start PHP built-in server** (router.php is required — handles token URLs with dots):
```powershell
php -S localhost:8080 -t "C:\Users\juerg\Claude\Turnierverwaltung_PHP" "C:\Users\juerg\Claude\Turnierverwaltung_PHP\router.php"
```

App runs on **http://localhost:8080**. The database schema is created automatically on first request via `init_db()` in `db.php`.

**Dependencies** (already installed):
```powershell
cd "C:\Users\juerg\Claude\Turnierverwaltung_PHP"
php composer.phar install
```

Composer packages: `phpmailer/phpmailer`, `mpdf/mpdf`, `chillerlan/php-qrcode ^5.0`.

## Configuration

All settings are in `config.php` as constants read from environment variables with local defaults. No `.env` file — set environment variables or edit the fallback values directly. Without `MAIL_HOST`, email confirmation links are flashed to the UI instead of sent.

Key constants: `SECRET_KEY`, `ADMIN_EMAIL`, `DB_*`, `MAIL_*`, `APP_URL`, `UPLOAD_DIR`.

## Architecture

### Request lifecycle

`router.php` → real static files served directly; everything else → `index.php`.

`index.php` does:
1. Starts session, sets security headers
2. `require_once` the four globals: `config.php`, `db.php`, `helpers.php`, `auth.php`, `lib/tokens.php`
3. Calls `init_db()` on every request (idempotent — `CREATE TABLE IF NOT EXISTS`)
4. Regex-matches `REQUEST_URI` against the route table (`$routes` array)
5. `require_once` exactly **one** `routes/*.php` file and calls the matched action function

Route pattern: `[METHOD, '/path/{param}', 'handler_file', 'action_function']`. Parameters are extracted into `$params` array passed to the action function.

### Route handlers (`routes/`)

Each file defines one or more functions named after actions. All same-named functions across files (e.g., `show()`, `delete()`) are safe because only one file is included per request. Handlers call `require_edit()` / `require_admin()` at the top for protected actions.

Files: `auth.php`, `tournament.php`, `competition.php`, `player.php`, `registration.php`, `match_result.php`, `pdf.php`, `admin.php`.

### Database (`db.php`)

PDO singleton via `get_db()`. Four helpers used everywhere:
- `db_fetch(sql, params)` → `?array` (one row or null)
- `db_fetchall(sql, params)` → `array` (all rows)
- `db_insert(sql, params)` → `string` (last insert ID)
- `db_execute(sql, params)` → `int` (affected rows)

All queries use parameterized statements. Schema is defined inline in `init_db()`. **Schema migrations** for columns added after initial deployment are handled via try-catch `ALTER TABLE` statements at the bottom of `init_db()` — add new columns there, never in the `CREATE TABLE` block of existing tables.

### Templates (`templates/`)

Plain PHP. Pattern in every template:
```php
ob_start(); ?>
...HTML...
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
```

`_base.php` renders Bootstrap 5.3 layout with `$content`. Optional `$extra_js` string for page-specific `<script>` blocks. Always use `e($val)` (= `htmlspecialchars`) for output escaping. URL helper: `url('path/to/resource')`.

Alternatively, route handlers can call `render('path/template', ['var' => $val])` which calls `extract()` and then includes the template.

### Auth & roles

Three roles: `admin`, `editor`, `viewer`. Unauthenticated users can view public tournaments.
- `can_edit()` → admin or editor
- `is_admin()` → admin only
- `require_edit()` / `require_admin()` → redirect to login or 403

`ADMIN_EMAIL` in `config.php` is hard-coded as the admin account. `current_user()` uses a static cache (one DB query per request max).

### Token system (`lib/tokens.php`)

HMAC-SHA256 tokens in format `base64url(payload).base64url(timestamp).base64url(sig)`. Not compatible with Python's `itsdangerous`. Wrappers:
- `make_email_confirm_token()` / `verify_email_confirm_token()` — 24h
- `make_reset_token()` / `verify_reset_token()` — 1h, includes password hash to invalidate on change
- `make_manage_email_token()` / `verify_manage_email_token()` — 7 days, per-email magic link for registration self-service

### Email (`lib/mail.php`)

`send_mail(to, subject, html_body)` wraps PHPMailer. When `MAIL_HOST` is empty (dev), returns false and callers flash the link to the UI instead. `MAIL_TLS=true` → STARTTLS on the configured port; `false` → SMTPS.

### KO bracket logic (`lib/ko_bracket.php`)

- `ko_round` counts **down**: first round is the highest value (e.g. 8 for 8-player bracket), final is always `ko_round=2`
- `ko_position` is 0-based within a round
- Winner of `(ko_round, ko_position)` advances to `(ko_round/2, ko_position/2)`, fills `player1_id` if `ko_position % 2 == 0`, else `player2_id`
- `ko_round=3` is the sentinel for the 3rd-place match (non-power-of-2 value used as flag)
- `recompute_ko_from(cid, from_ko_round)` clears all downstream rounds and re-propagates all played results — call this after any KO result edit
- Byes are auto-advanced with score 1:0 immediately at draw time
- `advance_count=0` means group-only (no KO phase)
- `competition.mode`: `'groups_ko'` (default), `'ko_only'`, or `'double_ko'`
- **Seeding draw order**: players are sorted by `skill DESC, player_id` (or `skill ASC, player_id` for tennis mode). The `player_id` tiebreaker must match the display label ordering in `show()` — using `RAND()` would cause seeding labels to disagree with bracket positions for equal-skill players.
- `seeded_player_slots(cap)`: returns slot indices in seeding priority. S1=slot 0, S2=slot cap-1, S3/S4=shuffled centre pair of each half, etc. Positions within each tier are still randomly assigned via `shuffle()`.

### Double KO bracket logic (`lib/double_ko_bracket.php`)

- `match.bracket`: `'W'` = Winners Bracket, `'L'` = Losers Bracket, `'GF'` = Grand Final. Single-KO matches have `bracket=NULL`.
- WB `ko_round` counts up (1 = first round, k = WB final feeding into GF).
- LB has `2*(k-1)` rounds. Odd LB rounds are Minor (1:1 match count ratio from previous round), even rounds are Major (2:1 halving). Minor→Major: same position, player1. Major→Minor: halve position.
- WB R1 losers fold into LB R1; WB Rr (r≥2) losers drop to LB R(2r-2) in reversed position as player2.
- `recompute_double_ko(cid)` rebuilds all derived slots from scratch — call after any DKO result edit.

### Group standings (`lib/standings.php`)

`group_standings(group_id)` computes on the fly. Scoring: win=2pts, draw=1pt, loss=0pt. Tie-breaking: goal difference → goals scored.

### Round-robin schedule (`lib/round_robin.php`)

`round_robin_schedule(player_ids)` returns match pairs using the standard circle method. Handles odd counts by adding a bye slot.

### PDF & CSV exports (`lib/pdf.php`)

`mpdf()` factory always sets `tempDir = sys_get_temp_dir() . '/mpdf_tmp'` — required on Windows.

**QR codes** use `chillerlan/php-qrcode` v5. Use `outputBase64 => false` to get raw SVG, write to temp file, reference by path in `<img src="...">`. The v5 option name is `outputBase64` (not `imageBase64`). ECC level: `\chillerlan\QRCode\Common\EccLevel::M`.

Landscape PDFs use format `'A4-L'` (not `'A4 landscape'`).

Export functions:
- `generate_aushang_pdf(tid)` — tournament overview with QR code (public)
- `generate_groups_pdf(cid)` / `generate_ko_pdf(cid)` / `generate_match_cards_pdf(cid)`
- `generate_registrations_pdf(tid)` / `generate_registrations_csv(tid)` — includes change requests section
- `generate_tournament_players_pdf(tid)` / `generate_tournament_players_csv(tid)`
- `generate_players_registry_pdf()` / `generate_players_registry_csv()` — global player register
- `generate_competition_players_pdf(cid)` / `generate_competition_players_csv(cid)`

### Registration workflow

1. Player submits public form → `registration` row (status=pending) + `registration_competition` rows
2. Admin confirms/rejects via tournament page → after confirm, magic link email sent automatically
3. Player uses magic link (`/nennung/verwalten/{token}`) to withdraw or request competition changes
4. Change requests create `registration_change_request` + `registration_change_competition` rows
5. Admin processes change requests on tournament page

### Database schema (key tables)

| Table | Purpose |
|-------|---------|
| `tournament` | Top-level container |
| `competition` | Discipline within tournament; `phase`: setup→group→ko→done; `mode`: groups_ko/ko_only/double_ko; `show_seeding`, `seeding_order` ('asc'/'desc') for KO modes |
| `player` | Global player registry |
| `player_skill` | Per-sport skill values (PK: player_id + sport) |
| `competition_player` | Players assigned to a competition (with per-competition skill) |
| `grp` | Named groups (A, B, C…) within a competition |
| `group_player` | Players in a group |
| `match` | Group matches (`group_id IS NOT NULL`) and KO matches (`group_id IS NULL`, `ko_round` set); `bracket` column: NULL=single KO, 'W'/'L'/'GF'=double KO |
| `registration` | Public sign-up (status: pending/confirmed/rejected) |
| `registration_competition` | Which competitions a registration covers |
| `registration_change_request` | Withdraw or modify request from player |
| `registration_change_competition` | Per-competition changes in a modify request |
| `user` | App users with hashed passwords and role |
