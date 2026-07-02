# Monitor-Aktualisierungsintervall (10–300 s) & Gäste-Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das Auto-Reload-Intervall der Monitoransicht wird einstellbar (10–300 s in 10er-Schritten, Default 60 s), und der Monitor-Link wird auch für Gäste sichtbar (Einstellungen bleiben Editoren vorbehalten).

**Architecture:** Exakt das Muster der Zoom-Einstellung (`monitor_zoom`): neue Spalten `competition.monitor_reload` / `tournament.monitor_reload` (INT, Default 60), Helfer `monitor_reload_clamp()`, Zahlenfeld in beiden Monitor-Registern, Query-Override `reload`, Durchreichen an die iframes. Im Monitor-JS ersetzt der Wert die Konstante `PERIOD = 60000` — Reload weiterhin nur am Zyklusende; der 10-Minuten-Watchdog bleibt fest. Der Gäste-Link ist eine reine Template-Änderung (Monitor-Routen sind bereits öffentlich).

**Tech Stack:** PHP 8.3, MariaDB, Bootstrap 5.3, Vanilla JS. Kein Test-Framework — Verifikation über `php -l`, CLI-Checks (`php -r`) und curl gegen die laufende App.

**Spec:** `docs/superpowers/specs/2026-07-02-monitor-reload-design.md`

## Global Constraints

- Intervall-Bereich: **10–300** Sekunden, nur 10er-Schritte, Default **60**. Rundung kaufmännisch auf den nächsten 10er (55 → 60), danach klemmen (9 → 10, 301 → 300).
- Schema-Änderungen ausschließlich als try-catch-`ALTER TABLE` am Ende von `init_db()` in `db.php`.
- Feldname überall `monitor_reload`; UI-Texte Deutsch; Query-Parameter `reload`.
- Der 10-Minuten-Watchdog (`setTimeout(reloadNow, 600000)`) bleibt unverändert.
- Gäste-Link: keine Routen-/Rechteänderung — nur Templates; Monitor-**Einstellungen** bleiben `$can_edit`-gebunden.
- Shell-Kommandos in den Verifikationsschritten sind **Bash** (Git Bash), Arbeitsverzeichnis `C:\Users\juerg\claude\turnier`. MariaDB muss laufen.

---

### Task 1: Schema-Migration `monitor_reload`

**Files:**
- Modify: `db.php` (Migrations-Array am Ende von `init_db()`, Z. ~387–400)

**Interfaces:**
- Produces: Spalten `competition.monitor_reload INT NOT NULL DEFAULT 60` und `tournament.monitor_reload INT NOT NULL DEFAULT 60`.

- [ ] **Step 1: Migrationszeilen ergänzen**

Im `$migrations`-Array: nach der Zeile
`"ALTER TABLE competition ADD COLUMN monitor_zoom INT NOT NULL DEFAULT 100",` einfügen:

```php
        "ALTER TABLE competition ADD COLUMN monitor_reload INT NOT NULL DEFAULT 60",
```

und nach der Zeile `"ALTER TABLE tournament ADD COLUMN monitor_zoom INT NOT NULL DEFAULT 100",` einfügen:

```php
        "ALTER TABLE tournament ADD COLUMN monitor_reload INT NOT NULL DEFAULT 60",
```

- [ ] **Step 2: Lint**

Run: `php -l db.php`
Expected: `No syntax errors detected in db.php`

- [ ] **Step 3: Migration ausführen und Spalten prüfen**

Run (Bash):
```bash
php -r 'require "config.php"; require "db.php"; init_db();
print_r(db_fetch("SHOW COLUMNS FROM competition LIKE \x27monitor_reload\x27"));
print_r(db_fetch("SHOW COLUMNS FROM tournament LIKE \x27monitor_reload\x27"));'
```
Expected: zwei Arrays mit `[Field] => monitor_reload`, `[Type] => int(...)`, `[Default] => 60`.

- [ ] **Step 4: Commit**

```bash
git add db.php
git commit -m "feat: Schema – monitor_reload auf competition & tournament (Default 60)"
```

---

### Task 2: Helfer `monitor_reload_clamp()`

**Files:**
- Modify: `helpers.php` (direkt nach `monitor_zoom_clamp()` am Dateiende)

**Interfaces:**
- Produces: `monitor_reload_clamp(int $v): int` — rundet auf 10er-Schritt und klemmt auf 10–300. Verwendet in Task 3, 4 und 6.

- [ ] **Step 1: Funktion ergänzen**

In `helpers.php` direkt nach der Funktion `monitor_zoom_clamp()` einfügen:

```php
/**
 * Monitor-Aktualisierungsintervall normalisieren: auf 10-Sekunden-Schritt runden
 * und auf 10–300 s klemmen.
 */
function monitor_reload_clamp(int $v): int {
    return max(10, min(300, (int)round($v / 10) * 10));
}
```

- [ ] **Step 2: Lint + Verhalten prüfen**

Run (Bash):
```bash
php -l helpers.php && php -r 'require "config.php"; require "helpers.php";
var_dump(monitor_reload_clamp(9), monitor_reload_clamp(55), monitor_reload_clamp(301), monitor_reload_clamp(60), monitor_reload_clamp(0));'
```
Expected: `No syntax errors` und `int(10) int(60) int(300) int(60) int(10)`.

- [ ] **Step 3: Commit**

```bash
git add helpers.php
git commit -m "feat: monitor_reload_clamp() – Intervall auf 10er-Schritt runden, 10-300 klemmen"
```

---

### Task 3: Bewerbs-Monitor — Speichern & Durchreichen (`routes/competition.php`)

**Files:**
- Modify: `routes/competition.php` — `monitor()` (Z. ~765–776) und `monitor_settings()` (Z. ~779–795)

**Interfaces:**
- Consumes: `monitor_reload_clamp()` (Task 2), Spalte `competition.monitor_reload` (Task 1).
- Produces: Template-Variable `ov_reload` (`?int`, roher Query-Wert oder null) für Task 4; POST-Feld `monitor_reload` wird validiert gespeichert.

- [ ] **Step 1: `monitor()` — Query-Override durchreichen**

Im `render('competition/monitor', [...])`-Array nach der Zeile
`'ov_zoom'  => array_key_exists('zoom',  $_GET) ? (int)get_param('zoom')      : null,` einfügen:

```php
        'ov_reload' => array_key_exists('reload', $_GET) ? (int)get_param('reload')   : null,
```

- [ ] **Step 2: `monitor_settings()` — Wert validieren und speichern**

Nach der Zeile `$zoom = monitor_zoom_clamp((int)post('monitor_zoom', 100));` einfügen:

```php
    $reload = monitor_reload_clamp((int)post('monitor_reload', 60));
```

Und das UPDATE-Statement ersetzen:

```php
    db_execute(
        "UPDATE competition SET monitor_show_schedule=?, monitor_scroll_speed=?, monitor_scroll_mode=?, monitor_block_pause=?, monitor_max_cols=?, monitor_zoom=?, monitor_reload=? WHERE id=?",
        [$show_schedule, $speed, $mode, $pause, $max_cols, $zoom, $reload, $cid]
    );
```

- [ ] **Step 3: Lint**

Run: `php -l routes/competition.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add routes/competition.php
git commit -m "feat: Bewerbs-Monitor – Aktualisierungsintervall speichern & Query-Override"
```

---

### Task 4: Bewerbs-Monitor — Intervall im JS anwenden

**Files:**
- Modify: `templates/competition/monitor.php` (Settings-Block Z. ~16–33, JS-Konstante `PERIOD` Z. ~601)

**Interfaces:**
- Consumes: `ov_reload` (Task 3), `monitor_reload_clamp()` (Task 2), `$c['monitor_reload']` (Task 1).

- [ ] **Step 1: Intervall-Einstellung auflösen**

Im Settings-Block: nach `$ov_zoom  = $ov_zoom  ?? null;` einfügen:

```php
$ov_reload = $ov_reload ?? null;
```

Nach der `$mon_zoom`-Zeile (`$mon_zoom          = monitor_zoom_clamp(...);`) einfügen:

```php
// Aktualisierungsintervall (Auto-Reload) in Sekunden, 10–300 in 10er-Schritten (Default 60).
$mon_reload        = monitor_reload_clamp($ov_reload !== null ? (int)$ov_reload : (int)($c['monitor_reload'] ?? 60));
```

- [ ] **Step 2: JS-Konstante `PERIOD` aus der Einstellung setzen**

Die Zeile

```js
  var PERIOD = 60000;     // ms: frühestens nach so langer Zeit neu laden (am Zyklusende)
```

ersetzen durch:

```js
  var PERIOD = <?= (int)$mon_reload * 1000 ?>; // ms: frühestens nach so langer Zeit neu laden (am Zyklusende)
```

Der Sicherheits-Watchdog (`setTimeout(reloadNow, 600000);`) bleibt unverändert.

- [ ] **Step 3: Lint**

Run: `php -l templates/competition/monitor.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add templates/competition/monitor.php
git commit -m "feat: Bewerbs-Monitor – Auto-Reload-Intervall aus Einstellung statt Konstante"
```

---

### Task 5: UI — Zahlenfeld „Aktualisierung (Sek.)" im Monitor-Register des Bewerbs

**Files:**
- Modify: `templates/competition/show.php` (Monitor-Tab: nach dem „Größe"-Dropdown Z. ~244–251; Hinweistext Z. ~256–262)

**Interfaces:**
- Consumes: `$c['monitor_reload']` (Task 1); POST-Feld `monitor_reload` wird von `monitor_settings()` (Task 3) verarbeitet.

- [ ] **Step 1: Zahlenfeld einfügen**

Nach dem schließenden `</div>` des „Größe"-Blocks (`<select name="monitor_zoom" ...>...</select>` + `</div>`) und vor dem `<div class="col-auto">` mit dem Speichern-Button einfügen:

```php
      <div class="col-auto">
        <label class="form-label">Aktualisierung (Sek.)</label>
        <input type="number" name="monitor_reload" class="form-control form-control-sm" style="width:120px"
               min="10" max="300" step="10" value="<?= (int)($c['monitor_reload'] ?? 60) ?>">
      </div>
```

- [ ] **Step 2: Hinweistext ergänzen**

Im `form-text`-Block darunter nach der Zeile
`<br><strong>Größe</strong>: skaliert die gesamte Monitoranzeige (Schrift, Tabellen, Turnierbaum) — 100 % = Normalgröße.` anfügen:

```php
      <br><strong>Aktualisierung</strong>: Die Anzeige lädt frühestens nach Ablauf des Intervalls neu — jeweils am Ende des Scroll-Durchlaufs.
```

- [ ] **Step 3: Lint**

Run: `php -l templates/competition/show.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add templates/competition/show.php
git commit -m "feat: Monitor-Register Bewerb – Einstellung Aktualisierung (10-300 s)"
```

---

### Task 6: Turnier-Monitor — Einstellung, Speichern, Durchreichen an iframes

**Files:**
- Modify: `routes/tournament.php` — `monitor()` (Z. ~255–265) und `monitor_settings()` (Z. ~268–287)
- Modify: `templates/tournament/monitor.php` (Doc-Kommentar Z. 7 + `$embed_params` Z. ~14–21)
- Modify: `templates/tournament/show.php` (Monitor-Tab: nach dem „Größe"-Dropdown Z. ~414–421; Hinweistext Z. ~441–445)

**Interfaces:**
- Consumes: `monitor_reload_clamp()` (Task 2), Spalte `tournament.monitor_reload` (Task 1). Der `reload`-Query-Parameter wird vom Bewerbs-Monitor (Task 3/4) als Override interpretiert.
- Produces: Template-Variable `mon_reload` (int, bereits geklemmt) für `templates/tournament/monitor.php`.

- [ ] **Step 1: `monitor()` — Wert laden**

Im `render('tournament/monitor', [...])`-Array nach der `'mon_zoom'`-Zeile einfügen:

```php
        'mon_reload'        => monitor_reload_clamp((int)($t['monitor_reload'] ?? 60)),
```

- [ ] **Step 2: `monitor_settings()` — Wert speichern**

Nach der Zeile `$zoom = monitor_zoom_clamp((int)post('monitor_zoom', 100));` einfügen:

```php
    $reload = monitor_reload_clamp((int)post('monitor_reload', 60));
```

Und das UPDATE-Statement ersetzen:

```php
    db_execute(
        "UPDATE tournament SET monitor_show_schedule=?, monitor_scroll_speed=?, monitor_scroll_mode=?, monitor_block_pause=?, monitor_zoom=?, monitor_reload=?, monitor_competitions=? WHERE id=?",
        [$show_schedule, $speed, $mode, $pause, $zoom, $reload, $comp_csv, $tid]
    );
```

- [ ] **Step 3: `templates/tournament/monitor.php` — Parameter durchreichen**

Im Doc-Kommentar (Z. 7) die Variablenliste ersetzen durch:

```php
 * Erwartete Variablen: $t, $comps, $mon_show_schedule, $mon_scroll_speed, $mon_scroll_mode, $mon_block_pause, $mon_zoom, $mon_reload.
```

Im `$embed_params`-Array nach `'zoom'  => $mon_zoom,` einfügen:

```php
    'reload' => $mon_reload,
```

- [ ] **Step 4: `templates/tournament/show.php` — Zahlenfeld einfügen**

Nach dem schließenden `</div>` des „Größe"-Blocks (`<select name="monitor_zoom" ...>` mit `$t['monitor_zoom']`) und vor `<div class="col-12">` („Anzuzeigende Bewerbe") einfügen:

```php
      <div class="col-auto">
        <label class="form-label">Aktualisierung (Sek.)</label>
        <input type="number" name="monitor_reload" class="form-control form-control-sm" style="width:120px"
               min="10" max="300" step="10" value="<?= (int)($t['monitor_reload'] ?? 60) ?>">
      </div>
```

Im `form-text`-Block unter dem Formular nach der „Größe"-Zeile anfügen:

```php
      <br><strong>Aktualisierung</strong>: Reload-Intervall der Bewerbs-Spalten — frühestens nach Ablauf, jeweils am Ende des Scroll-Durchlaufs.
```

- [ ] **Step 5: Lint**

Run (Bash):
```bash
php -l routes/tournament.php && php -l templates/tournament/monitor.php && php -l templates/tournament/show.php
```
Expected: dreimal `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add routes/tournament.php templates/tournament/monitor.php templates/tournament/show.php
git commit -m "feat: Turnier-Monitor – Einstellung Aktualisierung, an Bewerbs-iframes durchgereicht"
```

---

### Task 7: Monitor-Link für Gäste (nur Templates)

**Files:**
- Modify: `templates/competition/show.php:55-60,106-108` (Kopfzeilen-Aktionsleiste)
- Modify: `templates/tournament/show.php:94-107` (Tab-Leiste)

**Interfaces:**
- Consumes: bestehende öffentliche Routen `GET /competition/{id}/monitor` und `GET /tournament/{id}/monitor` — keine Routen-/Rechteänderung.

- [ ] **Step 1: Bewerbsseite — Monitor-Icon aus dem `$can_edit`-Block lösen**

In `templates/competition/show.php` den Block

```php
  <?php if ($can_edit): ?>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="<?= url('competition/'.$c['id'].'/monitor') ?>" target="_blank"
       class="btn btn-outline-secondary btn-sm" title="Monitoransicht (Vollbild für Anzeige/Beamer)">
      <i class="bi bi-display"></i>
    </a>
    <?php if (!$locked && in_array($c['phase'], ['group','ko'], true)): ?>
```

ersetzen durch (Icon immer sichtbar, Editor-Buttons dahinter weiterhin nur bei `$can_edit`):

```php
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="<?= url('competition/'.$c['id'].'/monitor') ?>" target="_blank"
       class="btn btn-outline-secondary btn-sm" title="Monitoransicht (Vollbild für Anzeige/Beamer)">
      <i class="bi bi-display"></i>
    </a>
    <?php if ($can_edit): ?>
    <?php if (!$locked && in_array($c['phase'], ['group','ko'], true)): ?>
```

Und am Ende desselben Blocks (nach dem Löschen-Formular) das Muster

```php
    <?php endif; ?>
  </div>
  <?php endif; ?>
```

ersetzen durch:

```php
    <?php endif; ?>
    <?php endif; ?>
  </div>
```

- [ ] **Step 2: Turnierseite — „Monitor"-Link für Gäste in der Tab-Leiste**

In `templates/tournament/show.php` nach dem `<?php endif; ?>`, das den `$can_edit`-Block mit den Tabs „Einstellungen" und „Monitor" schließt (direkt vor `</ul>`), einfügen:

```php
  <?php if (!$can_edit): ?>
  <li class="nav-item" role="presentation">
    <a class="nav-link" href="<?= url('tournament/' . $t['id'] . '/monitor') ?>" target="_blank">
      <i class="bi bi-display me-1"></i>Monitor
    </a>
  </li>
  <?php endif; ?>
```

- [ ] **Step 3: Lint**

Run (Bash):
```bash
php -l templates/competition/show.php && php -l templates/tournament/show.php
```
Expected: zweimal `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add templates/competition/show.php templates/tournament/show.php
git commit -m "feat: Monitor-Link auch für Gäste sichtbar (Einstellungen bleiben Editoren)"
```

---

### Task 8: End-to-End-Verifikation

**Files:** keine Änderungen (nur Prüfung).

Voraussetzung: MariaDB läuft; PHP-Server starten (Bash, Hintergrund):
`php -S localhost:8080 -t "C:\Users\juerg\claude\Turnier" "C:\Users\juerg\claude\Turnier\router.php" &`
Testdaten: Bewerb 51 („Silber-Cup", Turnier 3, öffentlich) hat eine ausgeloste Gruppenphase.

- [ ] **Step 1: Query-Override prüfen**

```bash
curl -s "http://localhost:8080/competition/51/monitor?reload=10"  | grep -o "var PERIOD = [0-9]*"
curl -s "http://localhost:8080/competition/51/monitor?reload=55"  | grep -o "var PERIOD = [0-9]*"
curl -s "http://localhost:8080/competition/51/monitor?reload=999" | grep -o "var PERIOD = [0-9]*"
curl -s "http://localhost:8080/competition/51/monitor"            | grep -o "var PERIOD = [0-9]*"
```
Expected: `var PERIOD = 10000`, `var PERIOD = 60000`, `var PERIOD = 300000`, `var PERIOD = 60000` (Default).

- [ ] **Step 2: Speichern über die UI prüfen** (Login `dev-admin@local.test` / `devpass123`, Cookie-Jar + CSRF wie üblich)

`monitor_reload=120` per POST auf `/competition/51/monitor-settings` speichern (übrige Felder mit Bestandswerten mitsenden: `monitor_show_schedule=1&monitor_scroll_speed=medium&monitor_scroll_mode=smooth&monitor_block_pause=5&monitor_max_cols=4&monitor_zoom=100`). Danach:

```bash
curl -s "http://localhost:8080/competition/51/monitor" | grep -o "var PERIOD = [0-9]*"
```
Expected: `var PERIOD = 120000`. Anschließend Wert per DB auf 60 zurücksetzen
(`UPDATE competition SET monitor_reload=60 WHERE id=51`).

- [ ] **Step 3: Turnier-Monitor-Durchreichung prüfen**

Analog `monitor_reload=90` auf `/tournament/3/monitor-settings` speichern — **alle** Bestandsfelder mitsenden, sonst werden sie zurückgesetzt (`monitor_show_schedule=1&monitor_scroll_speed=medium&monitor_scroll_mode=smooth&monitor_block_pause=5&monitor_zoom=100&monitor_competitions[]=50&monitor_competitions[]=51&monitor_competitions[]=49`). Danach:

```bash
curl -s "http://localhost:8080/tournament/3/monitor" | grep -o 'reload=[0-9]*' | sort -u
```
Expected: `reload=90` (in allen iframe-URLs). Anschließend per DB zurücksetzen
(`UPDATE tournament SET monitor_reload=60 WHERE id=3`).

- [ ] **Step 4: Gäste-Sichtbarkeit prüfen (ohne Login/Cookies)**

```bash
# Bewerbsseite: Monitor-Icon-Link vorhanden, aber kein Monitor-Einstellungs-Formular
curl -s "http://localhost:8080/competition/51" | grep -c "competition/51/monitor\""
curl -s "http://localhost:8080/competition/51" | grep -c "monitor-settings"
# Turnierseite: Monitor-Link vorhanden, aber kein Einstellungs-Formular
curl -s "http://localhost:8080/tournament/3" | grep -c "tournament/3/monitor\""
curl -s "http://localhost:8080/tournament/3" | grep -c "monitor-settings"
```
Expected: erste Zahl je Seite ≥ 1 (Link vorhanden), `monitor-settings`-Zähler je Seite `0`.

- [ ] **Step 5: Editor-Sichtbarkeit unverändert**

Mit Login-Cookie dieselben vier Befehle: `monitor-settings` je Seite ≥ 1 (Register vorhanden), Monitor-Links ebenfalls vorhanden.

- [ ] **Step 6: Abschluss**

```bash
git status
```
Expected: sauberer Arbeitsbaum (Testdaten wurden per DB zurückgesetzt); andernfalls Korrekturen mit `fix:`-Message committen.
