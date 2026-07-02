# Design: Monitoransicht – Größe (Zoom) in 10%-Schritten einstellbar

**Datum:** 2026-07-02
**Status:** Vom Benutzer genehmigt

## Ziel

Die Monitoransicht (Bewerbs-Monitor und Turnier-Monitor) soll in ihrer Gesamtgröße
skalierbar sein: 50 % bis 200 % in 10%-Schritten, Default 100 %. Damit lässt sich die
Darstellung an Bildschirmgröße und Betrachtungsabstand anpassen (verkleinern = mehr
Inhalt sichtbar, vergrößern = Fernsicht).

## Ansatz

CSS-Eigenschaft `zoom` auf dem Seiteninhalt des Bewerbs-Monitors. `zoom` skaliert
alles einheitlich — auch die fixen Pixelwerte (KO-Baum `slot_h = 150px`,
Kästchenzeilen 42px, Grid `minmax(440px, …)`), das Layout fließt korrekt neu und
Auto-Scroll bleibt nutzbar. Seit 2024 in allen gängigen Browsern standardisiert.

Verworfene Alternativen:
- `html { font-size }` skalieren: erfasst nur rem-basierte Größen, fixe px-Werte
  blieben unskaliert → inkonsistentes Bild.
- `transform: scale()`: skaliert nur visuell; Layout- und Scrollmaße behalten die
  Originalgröße → Scrollverhalten bricht.

## Komponenten

### 1. Schema (`db.php`)

- `competition.monitor_zoom` INT NOT NULL DEFAULT 100
- `tournament.monitor_zoom` INT NOT NULL DEFAULT 100
- Beide als try-catch-`ALTER TABLE` am Ende von `init_db()` (Projektkonvention,
  nicht im `CREATE TABLE`-Block).

### 2. Bewerbs-Monitor

- **Einstellung:** Dropdown „Größe" (50 % … 200 %, 10er-Schritte) im Register
  „Monitor" von `templates/competition/show.php`, neben den bestehenden Optionen.
- **Speichern:** `monitor_settings()` in `routes/competition.php` — Wert auf den
  nächsten 10er-Schritt runden (55 → 60) und auf 50–200 klemmen, in
  `monitor_zoom` speichern.
- **Anwenden:** `templates/competition/monitor.php` setzt bei Zoom ≠ 100 ein
  `zoom: <wert>%` auf dem Seiteninhalt (`body`).
- **Query-Override:** Parameter `zoom` hat — wie `sched`/`speed`/`mode`/`pause` —
  Vorrang vor dem Bewerbswert (gleiche Validierung). `monitor()` in
  `routes/competition.php` reicht ihn als `$ov_zoom` durch.

### 3. Turnier-Monitor

- **Einstellung:** gleiches Dropdown „Größe" im Monitor-Tab von
  `templates/tournament/show.php`; Speichern in `monitor_settings()` in
  `routes/tournament.php` (gleiche Validierung).
- **Anwenden:** `templates/tournament/monitor.php` reicht den Wert als
  Query-Parameter `zoom` in `$embed_params` an die eingebetteten Bewerbs-iframes
  weiter. Die schmale Kopfzeile des Turnier-Monitors bleibt unskaliert.

### 4. Zoom-sichere JS-Messungen (`templates/competition/monitor.php`)

Die bestehende Mess-/Scroll-Logik mischt `offsetTop`/`offsetHeight` (unskalierte,
lokale Werte) mit `window.innerHeight`/`getBoundingClientRect` (visuelle Werte).
Bei Zoom ≠ 100 % passen die Koordinatensysteme nicht mehr zusammen. Betroffen und
auf zoom-sichere Messungen (`getBoundingClientRect` + `scrollY`) umzustellen:

- `adjustTallTables()`: Tabellenhöhe via `getBoundingClientRect().height` statt
  `offsetHeight` mit `innerHeight` vergleichen.
- Blockweises Scrollen (`targetFor`): Blockziel via
  `getBoundingClientRect().top + window.scrollY` statt `offsetTop`.
- `drawWB()`/`drawLB()`: SVG-Verbindungslinien des Turnierbaums — gBCR-Koordinaten
  durch den effektiven Zoom (`bRect.width / bracket.offsetWidth`) teilen, da das
  Overlay-SVG in lokalen Einheiten rendert.

Unkritisch (bleibt): `positionThird()` und `setHeadOffset()` arbeiten vollständig
innerhalb des gezoomten Teilbaums bzw. konsistent im lokalen Koordinatensystem.

## Rechte & Audit

Keine Sonderbehandlung nötig — die bestehenden Gates der beiden
`monitor_settings()`-Handler (Turnier-Scope-Prüfung, Audit-Log via Gate) decken die
neue Option automatisch ab.

## Testen

- Bewerbs-Monitor bei 50 %, 100 %, 200 %: Layout skaliert einheitlich, Auto-Scroll
  (gleichmäßig und blockweise) erreicht Anfang/Ende bzw. die Blöcke korrekt,
  Sticky-Abschaltung zu hoher Tabellen greift weiterhin richtig.
- Turnier-Monitor: eingestellter Zoom wirkt in allen Bewerbs-Spalten (iframes),
  Kopfzeile unverändert.
- Einstellungen speichern/laden: Grenzwerte (49 → 50, 201 → 200, 55 → 60),
  Default 100 bei Bestandsdaten.
