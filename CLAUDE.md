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
php -S localhost:8080 -t "C:\Users\juerg\claude\Turnier" "C:\Users\juerg\claude\Turnier\router.php"
```

App läuft auf **http://localhost:8080**. Das Datenbankschema wird beim ersten Request automatisch via `init_db()` in `db.php` angelegt.

**Abhängigkeiten** (bereits installiert):
```powershell
cd "C:\Users\juerg\claude\Turnier"
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
- `can_edit()` → admin oder editor (rollenbasiert, **nicht** turnier-gebunden)
- `is_admin()` → nur admin
- `require_edit()` / `require_admin()` → Weiterleitung zum Login oder 403

**Turnier-gebundene Bearbeitungsrechte** (`auth.php`): Editoren dürfen **nur** Turniere bearbeiten,
denen sie zugeordnet sind (Tabelle `tournament_editor`); Admins dürfen alles. Maßgeblich ist:
- `can_edit_tournament(?int $tid)` → Admin immer true; Editor nur bei Zuordnung; Viewer/Gast nie.
- `require_tournament_edit(int $tid)` / `require_competition_edit(int $cid)` /
  `require_match_edit(int $mid)` → Login + Scope-Prüfung, sonst 403. `competition_tid($cid)` löst
  die Turnier-ID eines Bewerbs auf. **Alle** turnier-/bewerbs-gebundenen Handler (tournament,
  competition, match_result, registration, nicht-öffentliche pdf-Exporte) nutzen diese statt
  `require_edit()`. Öffentliche PDFs prüfen `is_public || can_edit_tournament(...)`.
- Rollenbasiert (global) bleiben: Turnier **anlegen** (`new_tournament`), Liste umsortieren
  (`reorder`), globales **Spielerregister**/Importe (`player.php`), Doppel-Stubs (`double.php`),
  Register-PDFs. Der Turnier-Ersteller wird automatisch in `tournament_editor` eingetragen.
- **Editor-Verwaltung** in den Turniereinstellungen (Settings-Tab von `tournament/show.php`):
  zugeordnete Editoren hinzufügen (Dropdown der `role='editor'`-Benutzer) / entfernen — erlaubt für
  Admins und bereits zugeordnete Editoren. Routen `POST /tournament/{id}/editors/add` und
  `…/editors/{uid}/remove` → `add_editor()`/`remove_editor()`. Templates (`tournament/show.php`,
  `competition/show.php`) nutzen das vom Handler übergebene `$can_edit` (= `can_edit_tournament`)
  statt `can_edit()`, damit Editor-Buttons nur bei tatsächlichem Bearbeitungsrecht erscheinen.
  Die Turnierliste (`index()`) zeigt Editoren zugeordnete **plus** öffentliche Turniere.
- Bestehende Turniere ohne Zuordnung sind nur für Admins bearbeitbar (keine Auto-Migration).

**Aktivitätsprotokoll / Audit-Log** (`helpers.php` `audit_log()`, Tabelle `audit_log`): protokolliert
privilegierte Aktionen (Funktionen, die Gästen nicht offenstehen). Zentrale Funktion `audit_log($status)`
wird ausschließlich aus den Auth-Gates (`auth.php`) aufgerufen — daher deckt sie automatisch **jede**
gate-geschützte Funktion ab. `$status='ok'` (erfolgreiche Aktion) wird **nur bei ändernden POST-Requests**
geschrieben; `$status='denied'` (verweigerter Zugriff via `_audit_deny()`) wird **immer** geschrieben (auch
GET/Gast — sicherheitsrelevant). Reine Ansichten/Exporte (privilegierte GET-Handler) werden bewusst nicht
protokolliert. Einmal pro Request (statischer Guard); Schreibfehler brechen den Request nie (try/catch).
Der Route-Kontext kommt aus `$GLOBALS['__audit_route']` (in `index.php` als `handler.action` gesetzt).
Pro Eintrag wird zusätzlich das **betroffene Objekt** lesbar aufgelöst (Spalte `target`): z.B.
Editor-/Benutzername bei Zuordnung/Rollenänderung, Spieler-/Team-/Doppelname bei Teilnehmer-Aktionen,
Begegnung (Teilnehmer) bei Ergebnissen, Nennungs-Person, Turnier-/Bewerbsname sonst. Auflösung über
`audit_target()` → `_audit_resolve_target($handler, $action, $params)` (nutzt `$GLOBALS['__audit_params']`,
also die Route-Parameter, sowie `$_POST`); da das Logging zur **Gate-Zeit** (vor der Aktion) läuft, sind
auch Namen zu löschenden Objekten noch verfügbar.
Nur Admins: Ansicht unter **`GET /admin/audit`** (Menü → Protokoll), Filter Alle/Aktionen/Verweigert,
**Volltextsuche** (`?q=` über Benutzer/Rolle/Aktion/Objekt/Pfad/IP) und Paginierung (100/Seite);
**`POST /admin/audit/clear`** leert das Protokoll (unbegrenzte Aufbewahrung, manuelles Leeren).
`audit_area_label($handler)` liefert die deutsche Bereichsbezeichnung für die Anzeige.

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

### Platzierungs-Brackets / Kreuzspiele (`lib/placement_bracket.php`, Modus `groups_cross`)

Nach der Gruppenphase wird **jeder Platz ausgespielt** (vollständige Platzierungsrunde). Pro
Rang-Paar (1+2, 3+4, …, `cross_config`) wählbar: **Kreuz** (1.A–2.B …, Finale + Spiel um Platz 3)
oder **getrennt** (1. untereinander, 2. untereinander). Daraus entstehen Platzblöcke `bracket='C0'/'C1'…`
(z.B. „Plätze 1-4", „5-8").
- Jeder Block (M Teilnehmer, auf S=`next_power_of_2` mit Byes) spielt rekursiv aus: je Runde
  spielen alle Aktiven, **Sieger → oberer Sub-Pool, Verlierer → unterer**; nach `log2(S)` Runden
  eindeutiger Platz. `ko_round` = Rundenindex 1..k, `ko_position` = globaler Index, `place_lo` =
  unterster Platz des Sub-Pools. Byes kaskadieren (lösen sich automatisch auf).
- `build_placement_blocks(cid, is_team, is_doubles, seeding_order, cross_flags)` → Blöcke aus den
  Gruppenplatzierungen (Crossover-Seeding byes-sicher wie `draw_ko`).
- `draw_placement(cid, blocks, …)` baut/seedet, `recompute_placement(cid)` propagiert nach jeder
  Ergebnisänderung (im `_propagate_result()`-Zweig), `placement_final_places(cid)` → [Platz→ID],
  `_maybe_set_done_placement(cid)`.
- Handler `draw_cross()` (Route `POST /competition/{id}/draw/cross`), PDF `generate_cross_pdf()`
  (Route `…/pdf/cross`). Anzeige der Blöcke oberhalb der Gruppen (Stage-Reihenfolge), Endplatzierung
  über die bestehende `$places`-Karte.

### Gruppenplatzierungen (`lib/standings.php`)

`group_standings(group_id)` (analog `team_standings`/`double_standings`) berechnet on the fly.
Wertung über die Bewerbsoption **`competition.points_mode`** („Punktevergabe"): `'2-1-0'` (Default,
Sieg/Unentsch./Niederl.), `'3-1-0'` oder `'3-2-1'`. `_parse_points_mode(mode)` → `[Sieg, Unent.,
Niederl.]`, `_points_for(group_id)` lädt den Modus über den Bewerb; die Standings-Funktionen und die
Mini-Tabellen in `_apply_h2h_tiebreaker()` / `_tied_ids_at_pos()` (Tie-Erkennung) nutzen ihn (inkl.
Niederlage-Punkten bei `'3-2-1'`). Bei Punktegleichstand entscheidet
`_apply_h2h_tiebreaker()` über zwei Kriterien-Blöcke: **Direktvergleich** (Mini-Tabelle der
Gleichpunktigen: Punkte→Tordiff→Einzeldiff→Tore→Einzel) und **Gesamt-Differenz** (Tordiff→
Einzeldiff→Tore→Einzel), danach `tiebreak_order` (manuell/Los).

Die Reihenfolge der beiden Blöcke steuert die Bewerbsoption **`competition.standings_order`**
(„Tabellenreihung"): `'h2h'` (Default) = Punkte→Direktvergleich→Differenz; `'diff'` = Punkte→
Differenz→Direktvergleich (Direktduell zuletzt vor manuell). `_standings_order(group_id)` lädt
den Modus, die Standings-Funktionen reichen ihn an `_apply_h2h_tiebreaker(..., $order_mode)` durch.
„Einzel" = Team-Einzelspiele (`team_match_duel`) bzw. Sätze (Satzmodus); sonst 0/ohne Wirkung.

### Round-Robin-Spielplan (`lib/round_robin.php`)

Gruppengröße: 3–24 Teilnehmer. Rundenbasierte Auslosung (Kreismethode): Der Plan wird in Runden gegliedert, in denen jeder Teilnehmer genau einmal spielt → gleichmäßige Verteilung über den gesamten Spielplan (kein „Front-Loading"). Zur Vermeidung von Back-to-Back (zwei Partien direkt hintereinander, v.a. an Rundenübergängen) werden mehrere zufällige Kandidaten erzeugt und der mit den wenigsten Back-to-Back-Übergängen gewählt (`rr_count_back_to_back`-frei ab 5 Teilnehmern immer möglich; bei 3/4 sind einzelne unvermeidbar). Zufall (wechselnde Spielpläne) durch Mischen von Teilnehmern, Runden, Spielreihenfolge und Seitenwahl.

- `round_robin_schedule(player_ids)` → flache Paarliste `[[p1,p2], …]` (rückwärtskompatibel).
- `round_robin_schedule_rounds(player_ids, force_bye=false)` → `['matches' => [['p1','p2','round'], …], 'byes' => [rundenNr => spielfreie_ID|null]]`. `draw_groups()` speichert `round_no` je `match`; die Spielfreien werden bei der Anzeige aus `round_no` + Gruppenmitglieder berechnet (nicht als Match-Zeile gespeichert). Der `byes`-Rückgabewert ist bei mehreren Spielfreien je Runde unvollständig und wird daher nicht genutzt.
- Bewerbsoption `competition.show_byes`: zeigt spielfreie Teilnehmer (ungerade Gruppen) als Inline-Zeile je Runde im Spielplan an — in der Web-Ansicht (`templates/competition/show.php`) und im Gruppen-PDF (`generate_groups_pdf`).
- Bewerbsoption `competition.force_byes` (alle Gruppen-Bewerbe): garantiert jedem Teilnehmer ≥1 spielfreie Runde. Bei gerader Anzahl fügt `rr_build_once` zwei Phantom-/Bye-Slots ein (Slot-Zahl bleibt gerade) → jedes Team i.d.R. 2 Pausen, Spielplan +1 Runde; bei ungerader Anzahl ohne Effekt (bereits 1 Pause). Wirkt nur bei `draw_groups()`/`groups_reorder()`. Web- und Gruppen-PDF-Anzeige unterstützen **mehrere** Spielfreie je Runde und werden bei `show_byes` **oder** `force_byes` angezeigt.

### Spielplätze / Courts (`lib/courts.php`)

Optional: `competition.num_courts` (0 = aus). Plätze sind an Gruppen gebunden (`grp.courts`,
komma-separiert), Begegnungen rotieren über die Plätze ihrer Gruppe; KO-Spiele nutzen den
gesamten Pool (`court = ko_position % num_courts + 1`, Finale = Platz 1).
- `default_group_courts(num_courts, num_groups)` → gleichmäßige Blöcke (z.B. (6,3)→[[1,2],[3,4],[5,6]]).
- `parse_courts(str, num_courts)` → normalisierte Platzliste (1..N, dedupe, sortiert).
- `assign_courts(cid)` schreibt `match.court_no`; **nach jedem Draw** (`draw_groups`,
  `groups_reorder`, `draw_ko`/`draw_ko_direct`, Doppel-KO) und nach `settings()` aufrufen.
- `draw_groups()` belegt `grp.courts` initial mit dem Default-Block (manuell editierbar via
  `save_courts()` / `POST /competition/{id}/courts`). Anzeige „<Court> X" in Web (Gruppe + KO) und
  in allen Match-PDFs inkl. Match-Cards.
- **Sportabhängige Bezeichnung** (`helpers.php`): `court_label(sport, plural)` → Singular/Plural
  je `tournament.sport` (tischtennis=Tisch/e, tennis=Tennisplatz/-plätze, fussball=Spielfeld/-er,
  cornhole=Bahn/en, sonst Platz/Plätze); `court_abbr(sport)` → Kurzform (Ti/Te/Fe/B/Pl) für den
  „B"-Spaltenkopf der Teampläne. Überall statt fixem „Platz" verwenden. „Platz" als **Rang**
  (Platzierung, „Spiel um Platz 3") bleibt davon unberührt.

### Anwurf-Auslosung (`lib/kickoff.php`, nur Team-Bewerbe)

Bewerbsoption `competition.kickoff_enabled` (0 = aus): legt je Gruppen-Begegnung zufällig,
aber über den gesamten Spielplan **ausgeglichen** fest, welches Team Anwurf hat
(`match.kickoff_team_id`). Pro Gruppe werden mehrere Kandidaten erzeugt und der beste gewählt
(`_kickoff_candidate`): streak-bewusster Greedy in Rundenreihenfolge (Anwurf bevorzugt an das
Team ohne Anwurf in der Vorrunde), Bewertung = benachbart gleiche Anwurf-Zustände je Team
(Abwechslung) + Ungleichgewicht. Ergebnis: jedes Team `floor/ceil((Spiele)/2)`-mal Anwurf und
max. Serie 2 (bei ungerader Spielzahl das Minimum).
- `assign_kickoff(cid)` schreibt/leert `match.kickoff_team_id`; nach `draw_groups()` und in
  `settings()` aufrufen (idempotent; bei Option aus oder Nicht-Team → alle auf NULL).
- Anzeige des Anwurf-Teams in Match-Cards (Header) und Teampläne-PDF (Spalte **An**); im Web-Spielplan wird der Anwurf nicht angezeigt (nur der Spielplatz steht kompakt links in der Begegnungszeile).
- Team-Start-Nr.: `team_start_numbers(group_id)` in `lib/standings.php` → `[team_id => 1..N]`,
  sortiert nach `skill DESC, team_id` (pro Gruppe).

### PDF- & CSV-Exporte (`lib/pdf.php`)

`mpdf()` Factory setzt immer `tempDir = sys_get_temp_dir() . '/mpdf_tmp'` — unter Windows erforderlich.

**QR-Codes** verwenden `chillerlan/php-qrcode` v5. `outputBase64 => false` für rohes SVG, in Temp-Datei schreiben, per Pfad in `<img src="...">` referenzieren. Der v5-Optionsname ist `outputBase64` (nicht `imageBase64`). ECC-Level: `\chillerlan\QRCode\Common\EccLevel::M`.

Querformat-PDFs verwenden Format `'A4-L'` (nicht `'A4 landscape'`).

Export-Funktionen:
- `generate_aushang_pdf(tid)` — Turnierübersicht mit QR-Code (öffentlich)
- `generate_competition_aushang_pdf(cid)` — Aushang je Bewerb: alle Teilnehmer je Gruppe auf EINER
  Seite (zwei Gruppen nebeneinander, ohne Linien/Nummerierung, Listen-Schriftgröße abhängig von der
  Gesamtanzahl); Logo (Turnier-Banner) rechts oben, QR-Code → Bewerbsseite unterhalb der Gruppen.
  Route `…/competition/{id}/aushang`, in der Bewerbskachel verlinkt.
- `generate_groups_pdf(cid)` / `generate_ko_pdf(cid)` / `generate_match_cards_pdf(cid)`
- `generate_team_strips_pdf(cid, ?gid)` — Teampläne (Querformat): Gruppenphase pro Team ein
  Spielplan-Streifen zum Ausfüllen (Spalten Dg/B/Ge/An/1..team_size/Su/Pu/Mannschaft + gespiegelter
  Block); zusätzlich KO-Phase und Kreuzspiele als kompakte Tabelle mit einer Zeile je Begegnung
  (Label/Platz/Mannschaft 1 + Ausfüllspalten + Mannschaft 2, möglichst viele je Seite).
  Datum = Turniertag (`tournament.event_date`), nicht das aktuelle Datum.
- `generate_court_plans_pdf(cid)` — Bahnpläne (alle Bewerbstypen, nur bei `num_courts > 0`): pro
  Spielplatz (`match.court_no`, 1..num_courts) ein Blatt mit der Spielreihenfolge — Spalten
  Nr./Phase-Runde/Teilnehmer 1/–/Teilnehmer 2, **ohne** Ausfüllfelder. Reihenfolge je Bahn
  chronologisch (Gruppen nach Runde/Match-Order, dann KO erste Runde zuerst, dann Kreuzspiele).
  Sportabhängige Bezeichnung über `court_label()` (Button-Label „<Bahn>pläne"). Route
  `…/competition/{id}/pdf/court-plans`.
- `generate_registrations_pdf(tid)` / `generate_registrations_csv(tid)` — inkl. Änderungsanträge
- `generate_tournament_players_pdf(tid)` / `generate_tournament_players_csv(tid)`
- `generate_players_registry_pdf()` / `generate_players_registry_csv()` — globales Spielerregister
- `generate_competition_players_pdf(cid)` / `generate_competition_players_csv(cid)`

### Excel/CSV-Import im Spielerregister (`routes/player.php`)

Drei Importe (je `require_edit()`, GET = Formular/Vorlage, POST = Verarbeitung), alle nutzen
das generische Template `templates/player/import.php` via `render('player/import', …)`:
- **Spieler**: `import_players()` / `import_template()` — Dedup über Pass-Nr. oder Nachname+Vorname.
- **Doppel**: `import_doubles()` / `import_doubles_template()` — eine Zeile je Doppel
  (Spieler 1 + Spieler 2 je `Nachname|Vorname|Pass-Nr.`).
- **Teams**: `import_teams()` / `import_teams_template()` — Langformat, eine Zeile je Mitglied,
  gruppiert nach `Teamname`. Zeilen mit nur ausgefülltem `Teamname` (Mitgliedsspalten leer)
  legen ein **Team ohne Mitglieder** an. `_xlsx_build_template(...)` akzeptiert via `$extraRows`
  mehrere Beispielzeilen.

Geteilte Helfer: `_xlsx_build_template(headers, example, sheet)` (XLSX-Vorlage), `_xlsx_parse()`
/ `_csv_parse()` (Einlesen), `_import_rows_from_upload()` (Upload→Datenzeilen).
**Mitglieder-Matching** `_resolve_or_create_player(name, firstname, passnr)`: Pass-Nr. vor
Name; mehrere Namens-Treffer → `error: mehrdeutig`; nicht gefunden → Spieler wird automatisch
angelegt (`created=true`). Dedup: Doppel über Paar (beide Reihenfolgen), Team über Namen.

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
| `competition` | Disziplin innerhalb eines Turniers; `phase`: setup→group→ko→done; `mode`: groups_ko/groups_cross/ko_only/double_ko; `show_seeding`, `seeding_order` ('desc'=höhere Stärke stärker / 'asc'=niedrigere Stärke stärker (Tennis) / 'random'=komplett zufällige Gruppen-/KO-Auslosung ohne Setzung); `show_byes` (spielfreie Teilnehmer im Gruppen-Spielplan anzeigen); `force_byes` (jedem Teilnehmer ≥1 spielfreie Runde garantieren, auch bei gerader Anzahl — Phantom-Slot, wirkt bei Auslosung); `num_courts` (Anzahl Spielplätze, 0 = aus); `team_result_mode` (Team-Begegnungsergebnis: 'wins' = je Einzelsieg 1 Punkt, 'sum' = Einzelergebnisse aufsummieren, 'total' = nur Gesamtergebnis eingeben — bei 'sum'/'total' entfallen die Einzel-Spalten; bei 'total' werden im Spielplan/Web keine Einzelspiele erfasst, nur das Gesamtergebnis, Match-Cards/Teampläne behalten aber `team_size` Einzelspiel-Felder); `match_card_mode` (nur Teambewerbe, Match-Cards-Layout: 'fields' = mit Spielerfeldern (nummerierte Einzelspiel-Zeilen, Default) / 'compact' = ohne Spielerfelder — kompaktes Layout je Mannschaft mit Score-Spalten 1..team_size + Summe, gekreuzten Unterschriften und Bahn/Anspiel/Runde-Zeile, `_match_card_team_compact_html()` in `lib/pdf.php`; Anspiel = Start-Nr. des Anwurf-Teams); `cross_config` (Modus groups_cross: pro Rang-Paar 'x'=Kreuz/'s'=getrennt, CSV); `kickoff_enabled` (Team: Anwurf je Gruppen-Begegnung zufällig & ausgeglichen auslosen); `standings_order` (Tabellenreihung: 'h2h'=Punkte→Direktvergleich→Differenz / 'diff'=Punkte→Differenz→Direktvergleich); `points_mode` (Punktevergabe: '2-1-0' Default / '3-1-0' / '3-2-1' = Sieg-Unentsch.-Niederl.); `schedule_enabled`/`schedule_duration`/`schedule_start` (Zeitplan: rundenbasierte Uhrzeiten — nur aktivierbar bei `num_courts>0` UND `show_byes`; Spieldauer/Runde in Min. + Startzeit HH:MM; Runde N startet `Startzeit+(N−1)·Spieldauer`, nur Gruppenphase) |
| `player` | Globales Spielerregister |
| `player_skill` | Spielstärke pro Sport (PK: player_id + sport) |
| `competition_player` | Einem Bewerb zugeordnete Spieler (mit bewerbs-spezifischer Spielstärke) |
| `grp` | Benannte Gruppen (A, B, C…) innerhalb eines Bewerbs; `courts` = komma-separierte Platzliste der Gruppe; `pause_start`/`pause_duration` = optionale Gruppen-Pause (nur bei aktivem Zeitplan+Spielrunden): eingeplant an der ersten Rundengrenze ≥ `pause_start`, Runden danach um die Dauer verschoben — angezeigt als „Pause · HH:MM–HH:MM Uhr" in Spielplan/Teamplänen/Bahnplänen (`group_round_time()`/`group_pause_window()` in `helpers.php`) |
| `group_player` | Spieler in einer Gruppe |
| `match` | Gruppenspiele (`group_id IS NOT NULL`, `round_no` = Runde der Kreismethode) und KO-Spiele (`group_id IS NULL`, `ko_round` gesetzt); `bracket`-Spalte: NULL=Einzel-KO, 'W'/'L'/'GF'=Doppel-KO, 'C0'/'C1'…=Platzierungs-Block (groups_cross); `court_no` = zugewiesener Spielplatz; `kickoff_team_id` = Team mit Anwurf (Team-Gruppenspiele, NULL = keins); `place_lo` = unterster Platz des Sub-Pools (Platzierungs-Bracket) |
| `registration` | Öffentliche Anmeldung (status: pending/confirmed/rejected) |
| `registration_competition` | Welche Bewerbe eine Anmeldung umfasst |
| `registration_change_request` | Abmelde- oder Änderungsantrag des Spielers |
| `registration_change_competition` | Bewerbs-spezifische Änderungen in einem Änderungsantrag |
| `user` | App-Benutzer mit gehashten Passwörtern und Rolle |
| `tournament_editor` | Zuordnung Editor↔Turnier (PK: tournament_id + user_id, FK CASCADE) — Editoren mit Bearbeitungsrecht für genau dieses Turnier und seine Bewerbe |
| `audit_log` | Aktivitätsprotokoll privilegierter Aktionen: `user_id`/`username`/`role` (Snapshot, kein FK — überlebt Benutzerlöschung), `method`, `path`, `action` (handler.action), `target` (lesbares betroffenes Objekt), `status` ('ok'/'denied'), `ip`, `created_at` |
