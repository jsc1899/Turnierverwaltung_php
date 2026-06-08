# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Sprache
Antworte immer auf Deutsch, auch wenn ich auf Englisch schreibe.

## Verhalten
- Erkläre Codeänderungen kurz und verständlich
- Frage nach, wenn etwas unklar ist

## Projekt

Turnierverwaltung PHP — Turnierverwaltungs-Webanwendung mit PHP 8.3 + MariaDB/MySQL. Deutschsprachige Oberfläche. PHP-Port eines parallelen Python/Flask-Projekts (`C:\Users\juerg\claude\Turnierverwaltung`).

## Lokal starten

**MariaDB starten** (kein Dienst, muss jedes Mal manuell gestartet werden):
```powershell
Start-Process "C:\Program Files\MariaDB 12.3\bin\mysqld.exe" -WindowStyle Hidden
```

**PHP Built-in Server starten** (router.php erforderlich — behandelt Token-URLs mit Punkten):
```powershell
php -S localhost:8080 -t "C:\Users\juerg\Claude\Turnierverwaltung_PHP" "C:\Users\juerg\Claude\Turnierverwaltung_PHP\router.php"
```

App läuft auf **http://localhost:8080**. Das Datenbankschema wird beim ersten Request automatisch via `init_db()` in `db.php` angelegt.

**Abhängigkeiten** (bereits installiert):
```powershell
cd "C:\Users\juerg\Claude\Turnierverwaltung_PHP"
php composer.phar install
```

Composer-Pakete: `phpmailer/phpmailer`, `mpdf/mpdf`, `chillerlan/php-qrcode ^5.0`.

## Konfiguration

Alle Einstellungen in `config.php` als Konstanten aus Umgebungsvariablen mit lokalen Fallback-Werten. Keine `.env`-Datei — Umgebungsvariablen setzen oder Fallback-Werte direkt bearbeiten. Ohne `MAIL_HOST` werden E-Mail-Bestätigungslinks statt versendet in der UI angezeigt (Flash).

Wichtige Konstanten: `SECRET_KEY`, `ADMIN_EMAIL`, `DB_*`, `MAIL_*`, `APP_URL`, `UPLOAD_DIR`.

## Architektur

### Request-Lifecycle

`router.php` → echte statische Dateien direkt ausliefern; alles andere → `index.php`.

`index.php` tut:
1. Session starten, Security-Header setzen
2. `require_once` der vier Globals: `config.php`, `db.php`, `helpers.php`, `auth.php`, `lib/tokens.php`
3. `init_db()` bei jedem Request aufrufen (idempotent — `CREATE TABLE IF NOT EXISTS`)
4. `REQUEST_URI` per Regex gegen die Routen-Tabelle (`$routes`-Array) matchen
5. Genau **eine** `routes/*.php`-Datei per `require_once` laden und die gematchte Action-Funktion aufrufen

Routen-Muster: `[METHOD, '/pfad/{param}', 'handler_datei', 'action_funktion']`. Parameter werden ins `$params`-Array extrahiert und an die Action-Funktion übergeben.

### Route-Handler (`routes/`)

Jede Datei definiert eine oder mehrere Funktionen, die nach Actions benannt sind. Gleichnamige Funktionen in verschiedenen Dateien (z.B. `show()`, `delete()`) sind sicher, weil pro Request nur eine Datei eingebunden wird. Handler rufen zu Beginn `require_edit()` / `require_admin()` auf.

Dateien: `auth.php`, `tournament.php`, `competition.php`, `player.php`, `registration.php`, `match_result.php`, `pdf.php`, `admin.php`.

### Datenbank (`db.php`)

PDO-Singleton via `get_db()`. Vier Hilfsfunktionen, die überall verwendet werden:
- `db_fetch(sql, params)` → `?array` (eine Zeile oder null)
- `db_fetchall(sql, params)` → `array` (alle Zeilen)
- `db_insert(sql, params)` → `string` (letzte Insert-ID)
- `db_execute(sql, params)` → `int` (betroffene Zeilen)

Alle Queries verwenden parametrisierte Statements. Das Schema ist inline in `init_db()` definiert. **Schema-Migrationen** für nach dem ersten Deployment ergänzte Spalten werden als try-catch `ALTER TABLE`-Statements am Ende von `init_db()` verwaltet — neue Spalten dort ergänzen, nie im `CREATE TABLE`-Block bestehender Tabellen.

### Templates (`templates/`)

Einfaches PHP. Muster in jedem Template:
```php
ob_start(); ?>
...HTML...
<?php
$content = ob_get_clean();
require __DIR__ . '/../_base.php';
```

`_base.php` rendert das Bootstrap-5.3-Layout mit `$content`. Optionaler `$extra_js`-String für seitenspezifische `<script>`-Blöcke. Für die Ausgabe immer `e($val)` (= `htmlspecialchars`) verwenden. URL-Hilfsfunktion: `url('pfad/zur/ressource')`.

Alternativ können Route-Handler `render('pfad/template', ['var' => $val])` aufrufen, was `extract()` ausführt und dann das Template einbindet.

### Auth & Rollen

Drei Rollen: `admin`, `editor`, `viewer`. Nicht angemeldete Benutzer können öffentliche Turniere ansehen.
- `can_edit()` → admin oder editor
- `is_admin()` → nur admin
- `require_edit()` / `require_admin()` → Weiterleitung zum Login oder 403

`ADMIN_EMAIL` in `config.php` ist fest als Admin-Konto hinterlegt. `current_user()` verwendet einen statischen Cache (maximal eine DB-Abfrage pro Request).

### Token-System (`lib/tokens.php`)

HMAC-SHA256-Tokens im Format `base64url(payload).base64url(timestamp).base64url(sig)`. Nicht kompatibel mit Pythons `itsdangerous`. Wrapper:
- `make_email_confirm_token()` / `verify_email_confirm_token()` — 24 Std.
- `make_reset_token()` / `verify_reset_token()` — 1 Std., enthält Passwort-Hash zur Invalidierung bei Passwortänderung
- `make_manage_email_token()` / `verify_manage_email_token()` — 7 Tage, Magic-Link pro E-Mail für Nennung-Self-Service

### E-Mail (`lib/mail.php`)

`send_mail(to, subject, html_body)` kapselt PHPMailer. Wenn `MAIL_HOST` leer ist (Dev), wird false zurückgegeben und der Aufrufer zeigt den Link per Flash in der UI an. `MAIL_TLS=true` → STARTTLS auf dem konfigurierten Port; `false` → SMTPS.

### KO-Bracket-Logik (`lib/ko_bracket.php`)

- `ko_round` zählt **abwärts**: erste Runde hat den höchsten Wert (z.B. 8 bei 8-Spieler-Bracket), das Finale hat immer `ko_round=2`
- `ko_position` ist 0-basiert innerhalb einer Runde
- Gewinner von `(ko_round, ko_position)` rückt vor zu `(ko_round/2, ko_position/2)`, belegt `player1_id` wenn `ko_position % 2 == 0`, sonst `player2_id`
- `ko_round=3` ist der Sentinel für das Spiel um Platz 3 (Nicht-Zweierpotenz als Flag)
- `recompute_ko_from(cid, from_ko_round)` löscht alle nachgelagerten Runden und propagiert alle gespielten Ergebnisse neu — nach jeder KO-Ergebnisänderung aufrufen
- Freilose werden beim Auslosen sofort mit Score 1:0 weitergerückt
- `advance_count=0` bedeutet nur Gruppenphase (keine KO-Phase)
- `competition.mode`: `'groups_ko'` (Standard), `'ko_only'` oder `'double_ko'`
- **Setzungs-Reihenfolge beim Auslosen**: Spieler werden nach `skill DESC, player_id` sortiert (bzw. `skill ASC, player_id` im Tennis-Modus). Der `player_id`-Tiebreaker muss mit der Anzeigelabel-Reihenfolge in `show()` übereinstimmen — `RAND()` würde dazu führen, dass Setzungslabels (z.B. „5-8") nicht mit den tatsächlichen Bracket-Positionen übereinstimmen, wenn mehrere Spieler gleiche Spielstärke haben.
- `seeded_player_slots(cap)`: gibt Slot-Indizes in Setzungspriorität zurück. S1=Slot 0, S2=Slot cap-1, S3/S4=zufällig vertauschtes Mittelpaar jeder Hälfte usw. Die Positionen innerhalb jeder Gruppe werden weiterhin per `shuffle()` zufällig vergeben.

### Doppel-KO-Bracket-Logik (`lib/double_ko_bracket.php`)

- `match.bracket`: `'W'` = Winners Bracket, `'L'` = Losers Bracket, `'GF'` = Grand Final. Einzel-KO-Spiele haben `bracket=NULL`.
- WB `ko_round` zählt aufwärts (1 = erste Runde, k = WB-Finale vor Grand Final).
- LB hat `2*(k-1)` Runden. Ungerade LB-Runden sind Minor (1:1-Verhältnis zur Vorrunde), gerade sind Major (2:1-Halbierung). Minor→Major: gleiche Position, player1. Major→Minor: Position halbieren.
- WB-R1-Verlierer kommen gefaltet in LB R1; WB-Rr-Verlierer (r≥2) fallen in LB R(2r-2) auf umgekehrter Position als player2.
- `recompute_double_ko(cid)` baut alle abgeleiteten Slots von Grund auf neu — nach jeder DKO-Ergebnisänderung aufrufen.

### Gruppenplatzierungen (`lib/standings.php`)

`group_standings(group_id)` berechnet on the fly. Wertung: Sieg=2 Pkt., Unentschieden=1 Pkt., Niederlage=0 Pkt. Tiebreaker: Tordifferenz → erzielte Tore.

### Round-Robin-Spielplan (`lib/round_robin.php`)

`round_robin_schedule(player_ids)` gibt Spielpaarungen nach der Standard-Kreis-Methode zurück. Ungerade Spieleranzahl wird durch einen Freilos-Slot ergänzt.

### PDF- & CSV-Exporte (`lib/pdf.php`)

`mpdf()` Factory setzt immer `tempDir = sys_get_temp_dir() . '/mpdf_tmp'` — unter Windows erforderlich.

**QR-Codes** verwenden `chillerlan/php-qrcode` v5. `outputBase64 => false` für rohes SVG, in Temp-Datei schreiben, per Pfad in `<img src="...">` referenzieren. Der v5-Optionsname ist `outputBase64` (nicht `imageBase64`). ECC-Level: `\chillerlan\QRCode\Common\EccLevel::M`.

Querformat-PDFs verwenden Format `'A4-L'` (nicht `'A4 landscape'`).

Export-Funktionen:
- `generate_aushang_pdf(tid)` — Turnierübersicht mit QR-Code (öffentlich)
- `generate_groups_pdf(cid)` / `generate_ko_pdf(cid)` / `generate_match_cards_pdf(cid)`
- `generate_registrations_pdf(tid)` / `generate_registrations_csv(tid)` — inkl. Änderungsanträge
- `generate_tournament_players_pdf(tid)` / `generate_tournament_players_csv(tid)`
- `generate_players_registry_pdf()` / `generate_players_registry_csv()` — globales Spielerregister
- `generate_competition_players_pdf(cid)` / `generate_competition_players_csv(cid)`

### Registrierungs-Workflow

1. Spieler sendet öffentliches Formular → `registration`-Zeile (status=pending) + `registration_competition`-Zeilen
2. Admin bestätigt/lehnt ab auf der Turnierseite → nach Bestätigung wird automatisch ein Magic-Link per E-Mail gesendet
3. Spieler nutzt Magic-Link (`/nennung/verwalten/{token}`) zum Abmelden oder Beantragen von Bewerbs-Änderungen
4. Änderungsanträge erzeugen `registration_change_request` + `registration_change_competition`-Zeilen
5. Admin bearbeitet Änderungsanträge auf der Turnierseite

### Datenbankschema (wichtige Tabellen)

| Tabelle | Zweck |
|---------|-------|
| `tournament` | Oberste Ebene |
| `competition` | Disziplin innerhalb eines Turniers; `phase`: setup→group→ko→done; `mode`: groups_ko/ko_only/double_ko; `show_seeding`, `seeding_order` ('asc'/'desc') für KO-Modi |
| `player` | Globales Spielerregister |
| `player_skill` | Spielstärke pro Sport (PK: player_id + sport) |
| `competition_player` | Einem Bewerb zugeordnete Spieler (mit bewerbs-spezifischer Spielstärke) |
| `grp` | Benannte Gruppen (A, B, C…) innerhalb eines Bewerbs |
| `group_player` | Spieler in einer Gruppe |
| `match` | Gruppenspiele (`group_id IS NOT NULL`) und KO-Spiele (`group_id IS NULL`, `ko_round` gesetzt); `bracket`-Spalte: NULL=Einzel-KO, 'W'/'L'/'GF'=Doppel-KO |
| `registration` | Öffentliche Anmeldung (status: pending/confirmed/rejected) |
| `registration_competition` | Welche Bewerbe eine Anmeldung umfasst |
| `registration_change_request` | Abmelde- oder Änderungsantrag des Spielers |
| `registration_change_competition` | Bewerbs-spezifische Änderungen in einem Änderungsantrag |
| `user` | App-Benutzer mit gehashten Passwörtern und Rolle |
