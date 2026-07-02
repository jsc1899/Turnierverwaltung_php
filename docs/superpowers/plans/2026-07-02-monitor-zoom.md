# Monitoransicht-Zoom (50–200 %, 10%-Schritte) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Die Monitoransicht (Bewerbs- und Turnier-Monitor) bekommt eine Einstellung „Größe" (50–200 % in 10%-Schritten, Default 100 %), umgesetzt über CSS `zoom`.

**Architecture:** Neue Spalten `competition.monitor_zoom` und `tournament.monitor_zoom` (INT, Default 100) analog zu den bestehenden `monitor_*`-Einstellungen. Der Bewerbs-Monitor wendet `zoom: X%` auf `body` an; Query-Parameter `zoom` überschreibt den Bewerbswert (wie `sched`/`speed`/`mode`/`pause`). Der Turnier-Monitor reicht seinen Wert als `zoom`-Query-Parameter an die eingebetteten iframes weiter. Zwei JS-Messstellen im Bewerbs-Monitor werden zoom-sicher gemacht (`getBoundingClientRect` statt `offset*`).

**Tech Stack:** PHP 8.3, MariaDB, Bootstrap 5.3, Vanilla JS. Kein Test-Framework im Projekt — Verifikation über `php -l`, CLI-Checks (`php -r`) und Browser-Prüfung.

**Spec:** `docs/superpowers/specs/2026-07-02-monitor-zoom-design.md`

## Global Constraints

- Zoom-Bereich: **50–200**, nur 10er-Schritte, Default **100**. Rundung kaufmännisch auf den nächsten 10er (55 → 60), danach klemmen (49 → 50, 201 → 200).
- Schema-Änderungen ausschließlich als try-catch-`ALTER TABLE` am Ende von `init_db()` in `db.php` — nie im `CREATE TABLE`-Block.
- UI-Texte auf Deutsch; Ausgabe-Escaping mit `e()`; Feldname überall `monitor_zoom`.
- Shell-Kommandos in den Verifikationsschritten sind **Bash** (Git Bash), Arbeitsverzeichnis `C:\Users\juerg\claude\turnier`.
- Voraussetzung für DB-/Browser-Checks: MariaDB läuft (`Start-Process "C:\Program Files\MariaDB 12.3\bin\mysqld.exe" -WindowStyle Hidden` in PowerShell).

---

### Task 1: Schema-Migration `monitor_zoom`

**Files:**
- Modify: `db.php:387-397` (Migrations-Array am Ende von `init_db()`)

**Interfaces:**
- Produces: Spalten `competition.monitor_zoom INT NOT NULL DEFAULT 100` und `tournament.monitor_zoom INT NOT NULL DEFAULT 100` — von Task 3–6 gelesen/geschrieben.

- [ ] **Step 1: Migrationszeilen ergänzen**

In `db.php` im `$migrations`-Array: nach der Zeile
`"ALTER TABLE competition ADD COLUMN monitor_max_cols INT NOT NULL DEFAULT 0",` einfügen:

```php
        "ALTER TABLE competition ADD COLUMN monitor_zoom INT NOT NULL DEFAULT 100",
```

und nach der Zeile `"ALTER TABLE tournament ADD COLUMN monitor_competitions TEXT",` einfügen:

```php
        "ALTER TABLE tournament ADD COLUMN monitor_zoom INT NOT NULL DEFAULT 100",
```

- [ ] **Step 2: Lint**

Run: `php -l db.php`
Expected: `No syntax errors detected in db.php`

- [ ] **Step 3: Migration ausführen und Spalten prüfen** (MariaDB muss laufen)

Run (Bash):
```bash
php -r 'require "config.php"; require "db.php"; init_db();
print_r(db_fetch("SHOW COLUMNS FROM competition LIKE \x27monitor_zoom\x27"));
print_r(db_fetch("SHOW COLUMNS FROM tournament LIKE \x27monitor_zoom\x27"));'
```
Expected: zwei Arrays mit `[Field] => monitor_zoom`, `[Type] => int(...)`, `[Default] => 100`.

- [ ] **Step 4: Commit**

```bash
git add db.php
git commit -m "feat: Schema – monitor_zoom auf competition & tournament (Default 100)"
```

---

### Task 2: Helfer `monitor_zoom_clamp()`

**Files:**
- Modify: `helpers.php` (Funktion am Dateiende ergänzen)

**Interfaces:**
- Produces: `monitor_zoom_clamp(int $v): int` — rundet auf 10er-Schritt und klemmt auf 50–200. Verwendet in Task 3, 4 und 6.

- [ ] **Step 1: Funktion ergänzen**

Am Ende von `helpers.php`:

```php
/**
 * Monitor-Zoom normalisieren: auf 10%-Schritt runden und auf 50–200 klemmen.
 */
function monitor_zoom_clamp(int $v): int {
    return max(50, min(200, (int)round($v / 10) * 10));
}
```

- [ ] **Step 2: Lint + Verhalten prüfen**

Run (Bash):
```bash
php -l helpers.php && php -r 'require "config.php"; require "helpers.php";
var_dump(monitor_zoom_clamp(49), monitor_zoom_clamp(55), monitor_zoom_clamp(201), monitor_zoom_clamp(100), monitor_zoom_clamp(0));'
```
Expected: `No syntax errors` und `int(50) int(60) int(200) int(100) int(50)`.

Hinweis: Falls `require "helpers.php"` per CLI an fehlenden Abhängigkeiten scheitert (z.B. Session-/DB-Aufrufe beim Laden), stattdessen die vier Requires wie in `index.php` laden (`config.php`, `db.php`, `helpers.php`, `auth.php`) — die Funktion selbst hat keine Abhängigkeiten.

- [ ] **Step 3: Commit**

```bash
git add helpers.php
git commit -m "feat: monitor_zoom_clamp() – Zoom auf 10er-Schritt runden, 50-200 klemmen"
```

---

### Task 3: Bewerbs-Monitor — Speichern & Durchreichen (`routes/competition.php`)

**Files:**
- Modify: `routes/competition.php:765-774` (`monitor()`) und `routes/competition.php:778-793` (`monitor_settings()`)

**Interfaces:**
- Consumes: `monitor_zoom_clamp()` aus Task 2, Spalte `competition.monitor_zoom` aus Task 1.
- Produces: Template-Variable `ov_zoom` (`?int`, roher Query-Wert oder null) für `templates/competition/monitor.php` (Task 4); POST-Feld `monitor_zoom` wird validiert gespeichert.

- [ ] **Step 1: `monitor()` — Query-Override durchreichen**

In `routes/competition.php`, im `render('competition/monitor', [...])`-Array nach der Zeile
`'ov_pause' => array_key_exists('pause', $_GET) ? (int)get_param('pause')     : null,` einfügen:

```php
        'ov_zoom'  => array_key_exists('zoom',  $_GET) ? (int)get_param('zoom')      : null,
```

- [ ] **Step 2: `monitor_settings()` — Wert validieren und speichern**

Nach der Zeile `$max_cols = max(0, min(8, (int)post('monitor_max_cols', 0)));` einfügen:

```php
    $zoom = monitor_zoom_clamp((int)post('monitor_zoom', 100));
```

Und das UPDATE-Statement ersetzen:

```php
    db_execute(
        "UPDATE competition SET monitor_show_schedule=?, monitor_scroll_speed=?, monitor_scroll_mode=?, monitor_block_pause=?, monitor_max_cols=?, monitor_zoom=? WHERE id=?",
        [$show_schedule, $speed, $mode, $pause, $max_cols, $zoom, $cid]
    );
```

- [ ] **Step 3: Lint**

Run: `php -l routes/competition.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add routes/competition.php
git commit -m "feat: Bewerbs-Monitor – Zoom speichern & Query-Override durchreichen"
```

---

### Task 4: Bewerbs-Monitor — Zoom anwenden + zoom-sichere JS-Messungen

**Files:**
- Modify: `templates/competition/monitor.php` (Settings-Block Z. 16–31, `<style>`-Block Z. 190 ff., JS `adjustTallTables()` Z. 555–566 und `targetFor()` Z. 605)

**Interfaces:**
- Consumes: `ov_zoom` aus Task 3, `monitor_zoom_clamp()` aus Task 2, `$c['monitor_zoom']` aus Task 1.

- [ ] **Step 1: Zoom-Einstellung auflösen**

Im Settings-Block: nach `$ov_pause = $ov_pause ?? null;` einfügen:

```php
$ov_zoom  = $ov_zoom  ?? null;
```

Nach der `$mon_block_pause`-Zeile einfügen:

```php
// Größe (Zoom) der gesamten Anzeige, 50–200 % in 10er-Schritten (Default 100).
$mon_zoom          = monitor_zoom_clamp($ov_zoom !== null ? (int)$ov_zoom : (int)($c['monitor_zoom'] ?? 100));
```

- [ ] **Step 2: CSS `zoom` auf `body` anwenden**

Im `<style>`-Block direkt nach der Zeile
`body { background: var(--bs-secondary-bg); color: var(--bs-body-color); }` einfügen:

```php
<?php if ($mon_zoom !== 100): ?>
    body { zoom: <?= (int)$mon_zoom ?>%; }
<?php endif; ?>
```

- [ ] **Step 3: `adjustTallTables()` zoom-sicher machen**

Die Zeile

```js
      var tooTall = el.offsetHeight > (avail - 48);
```

ersetzen durch (Messung in visuellen Pixeln, damit sie bei Zoom ≠ 100 % zu `innerHeight` passt):

```js
      var tooTall = el.getBoundingClientRect().height > (avail - 48);
```

Den darüberstehenden Kommentar `// Zum Messen ggf. kurz die Off-Markierung ignorieren (offsetHeight ist davon unabhängig).` ersetzen durch:

```js
      // getBoundingClientRect liefert visuelle Pixel (zoom-sicher, konsistent zu innerHeight).
```

- [ ] **Step 4: Blockziel des blockweisen Scrollens zoom-sicher machen**

Die Zeile

```js
    function targetFor(i) { return Math.min(maxScroll(), Math.max(0, blocks[i].offsetTop - 14)); }
```

ersetzen durch:

```js
    function targetFor(i) {
      // Dokumentposition in visuellen Pixeln (zoom-sicher; offsetTop wäre bei zoom≠100% falsch).
      var top = blocks[i].getBoundingClientRect().top + window.scrollY;
      return Math.min(maxScroll(), Math.max(0, top - 14));
    }
```

- [ ] **Step 5: Lint**

Run: `php -l templates/competition/monitor.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add templates/competition/monitor.php
git commit -m "feat: Bewerbs-Monitor – CSS-Zoom anwenden, Scroll-/Messlogik zoom-sicher"
```

---

### Task 5: UI — Dropdown „Größe" im Monitor-Register des Bewerbs

**Files:**
- Modify: `templates/competition/show.php:235-243` (Monitor-Tab, nach dem `monitor_max_cols`-Feld)

**Interfaces:**
- Consumes: `$c['monitor_zoom']` (Task 1); POST-Feld `monitor_zoom` wird von `monitor_settings()` (Task 3) verarbeitet.

- [ ] **Step 1: Dropdown einfügen**

Nach dem schließenden `</div>` des `monitor_max_cols`-Blocks (Zeile `</select>` + `</div>` nach `max. <?= $n ?>`) und vor dem `<div class="col-auto">` mit dem Speichern-Button einfügen:

```php
      <div class="col-auto">
        <label class="form-label">Größe</label>
        <select name="monitor_zoom" class="form-select form-select-sm">
          <?php for ($z = 50; $z <= 200; $z += 10): ?>
          <option value="<?= $z ?>"<?= (int)($c['monitor_zoom'] ?? 100) === $z ? ' selected' : '' ?>><?= $z ?> %</option>
          <?php endfor; ?>
        </select>
      </div>
```

- [ ] **Step 2: Hinweistext ergänzen**

Im `form-text`-Block darunter (nach dem Satz zu „Gruppentabellen nebeneinander") anfügen:

```php
      <br><strong>Größe</strong>: skaliert die gesamte Monitoranzeige (Schrift, Tabellen, Turnierbaum) — 100 % = Normalgröße.
```

- [ ] **Step 3: Lint**

Run: `php -l templates/competition/show.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add templates/competition/show.php
git commit -m "feat: Monitor-Register Bewerb – Einstellung Größe (50-200%)"
```

---

### Task 6: Turnier-Monitor — Einstellung, Speichern, Durchreichen an iframes

**Files:**
- Modify: `routes/tournament.php:241-264` (`monitor()`) und `routes/tournament.php:267-285` (`monitor_settings()`)
- Modify: `templates/tournament/monitor.php:7,14-20` (Doc-Kommentar + `$embed_params`)
- Modify: `templates/tournament/show.php:409-413` (Monitor-Tab, nach dem Block-Pause-Feld)

**Interfaces:**
- Consumes: `monitor_zoom_clamp()` (Task 2), Spalte `tournament.monitor_zoom` (Task 1). Der `zoom`-Query-Parameter wird vom Bewerbs-Monitor (Task 3/4) als Override interpretiert.
- Produces: Template-Variable `mon_zoom` (int, bereits geklemmt) für `templates/tournament/monitor.php`.

- [ ] **Step 1: `monitor()` — Wert laden**

In `routes/tournament.php` im `render('tournament/monitor', [...])`-Array nach der `'mon_block_pause'`-Zeile einfügen:

```php
        'mon_zoom'          => monitor_zoom_clamp((int)($t['monitor_zoom'] ?? 100)),
```

- [ ] **Step 2: `monitor_settings()` — Wert speichern**

Nach der Zeile `$pause = max(1, min(120, (int)post('monitor_block_pause', 5)));` einfügen:

```php
    $zoom = monitor_zoom_clamp((int)post('monitor_zoom', 100));
```

Und das UPDATE-Statement ersetzen:

```php
    db_execute(
        "UPDATE tournament SET monitor_show_schedule=?, monitor_scroll_speed=?, monitor_scroll_mode=?, monitor_block_pause=?, monitor_zoom=?, monitor_competitions=? WHERE id=?",
        [$show_schedule, $speed, $mode, $pause, $zoom, $comp_csv, $tid]
    );
```

- [ ] **Step 3: `templates/tournament/monitor.php` — Parameter durchreichen**

Im Doc-Kommentar (Z. 7) die Variablenliste um `$mon_zoom` ergänzen:

```php
 * Erwartete Variablen: $t, $comps, $mon_show_schedule, $mon_scroll_speed, $mon_scroll_mode, $mon_block_pause, $mon_zoom.
```

Im `$embed_params`-Array nach `'pause' => $mon_block_pause,` einfügen:

```php
    'zoom'  => $mon_zoom,
```

- [ ] **Step 4: `templates/tournament/show.php` — Dropdown einfügen**

Nach dem schließenden `</div>` des Blocks `id="t-field-block-pause"` (Verweildauer je Block) und vor `<div class="col-12">` („Anzuzeigende Bewerbe") einfügen:

```php
      <div class="col-auto">
        <label class="form-label">Größe</label>
        <select name="monitor_zoom" class="form-select form-select-sm">
          <?php for ($z = 50; $z <= 200; $z += 10): ?>
          <option value="<?= $z ?>"<?= (int)($t['monitor_zoom'] ?? 100) === $z ? ' selected' : '' ?>><?= $z ?> %</option>
          <?php endfor; ?>
        </select>
      </div>
```

Im `form-text`-Block unter dem Formular anfügen:

```php
      <br><strong>Größe</strong>: skaliert den Inhalt aller Bewerbs-Spalten — 100 % = Normalgröße (Kopfzeile bleibt unverändert).
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
git commit -m "feat: Turnier-Monitor – Einstellung Größe, Zoom an Bewerbs-iframes durchgereicht"
```

---

### Task 7: End-to-End-Verifikation im Browser

**Files:** keine Änderungen (nur Prüfung; Fixes gehören zum jeweiligen Task zurück).

- [ ] **Step 1: Server starten** (PowerShell; MariaDB läuft bereits aus Task 1)

```powershell
php -S localhost:8080 -t "C:\Users\juerg\claude\Turnier" "C:\Users\juerg\claude\Turnier\router.php"
```

- [ ] **Step 2: Query-Override prüfen** (Bash; `<CID>` = ID eines Bewerbs mit ausgeloster Gruppenphase)

```bash
curl -s "http://localhost:8080/competition/<CID>/monitor?zoom=50"  | grep -o "zoom: [0-9]*%"
curl -s "http://localhost:8080/competition/<CID>/monitor?zoom=200" | grep -o "zoom: [0-9]*%"
curl -s "http://localhost:8080/competition/<CID>/monitor?zoom=55"  | grep -o "zoom: [0-9]*%"
curl -s "http://localhost:8080/competition/<CID>/monitor"          | grep -c "zoom:"
```
Expected: `zoom: 50%`, `zoom: 200%`, `zoom: 60%`; letzter Befehl `0` (bei gespeichertem Wert 100 wird keine zoom-Regel ausgegeben).

- [ ] **Step 3: Speichern über die UI prüfen**

Als Editor/Admin anmelden (Dev-Testkonten laut Memory: `dev-admin@local.test` / `devpass123`), Bewerb → Register „Monitor" → Größe „130 %" wählen → Speichern. Danach:

```bash
curl -s "http://localhost:8080/competition/<CID>/monitor" | grep -o "zoom: [0-9]*%"
```
Expected: `zoom: 130%`

- [ ] **Step 4: Scrollverhalten bei Zoom prüfen** (Browser, manuell)

Monitoransicht mit `?zoom=150` öffnen (Bewerb mit genug Inhalt zum Scrollen):
- Gleichmäßiger Modus: scrollt bis ganz unten und wieder hoch (kein vorzeitiges Umkehren, kein Hängen am Ende).
- Blockmodus (`&mode=block`): jeder Abschnitt (Endplatzierung/Gruppen/KO) wird korrekt angefahren, Blockanfang sitzt knapp unter dem Kopf.
- Sticky-Tabellen: bei `?zoom=200` werden zu hohe Gruppentabellen nicht fixiert (Tabellenende bleibt erreichbar).

- [ ] **Step 5: Turnier-Monitor prüfen** (Browser, manuell)

Turnier → Register „Monitor" → Größe z.B. „80 %" speichern → „Turnier-Monitor öffnen": alle Bewerbs-Spalten sind verkleinert, die Kopfzeile des Turnier-Monitors bleibt in Normalgröße. iframe-URLs enthalten `zoom=80`.

- [ ] **Step 6: Abschluss-Commit (falls in Task 7 noch Korrekturen anfielen)**

```bash
git status
```
Expected: sauberer Arbeitsbaum; andernfalls Korrekturen mit passender `fix:`-Message committen.
