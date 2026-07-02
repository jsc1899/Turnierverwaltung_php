# Design: Monitoransicht – Aktualisierungsintervall einstellbar (10–300 s)

**Datum:** 2026-07-02
**Status:** Vom Benutzer genehmigt

## Ziel

Das Auto-Reload-Intervall der Monitoransicht (Bewerbs- und Turnier-Monitor) soll
einstellbar sein: 10 bis 300 Sekunden in 10-Sekunden-Schritten, Standard 60 s.
Bisher ist es als Konstante `PERIOD = 60000` ms im Monitor-JS fest verdrahtet.

## Ansatz

Exakt das Muster der bestehenden Zoom-Einstellung (`monitor_zoom`,
Spec `2026-07-02-monitor-zoom-design.md`): Spalten auf beiden Ebenen,
Klemm-Helfer, Feld in beiden Monitor-Registern, Query-Override, Durchreichen an
die iframes. Die Reload-Semantik bleibt unverändert: neu geladen wird **am Ende
des Scroll-Zyklus**, frühestens nach Ablauf des Intervalls — kein Abbruch mitten
im Durchlauf.

Verworfene Alternativen:
- Hartes `setTimeout`-Reload unabhängig vom Scroll-Zyklus: unterbricht die
  Anzeige mitten im Durchlauf.
- AJAX-Teilaktualisierung ohne Seiten-Reload: großer Umbau ohne Bedarf (YAGNI).

## Komponenten

### 1. Schema (`db.php`)

- `competition.monitor_reload` INT NOT NULL DEFAULT 60
- `tournament.monitor_reload` INT NOT NULL DEFAULT 60
- Beide als try-catch-`ALTER TABLE` am Ende von `init_db()` (Projektkonvention).

### 2. Helfer (`helpers.php`)

`monitor_reload_clamp(int $v): int` — kaufmännisch auf den nächsten
10er-Schritt runden (55 → 60), dann auf 10–300 klemmen (9 → 10, 301 → 300).
Analog zu `monitor_zoom_clamp()` direkt daneben platziert.

### 3. Bewerbs-Monitor

- **Einstellung:** Zahlenfeld „Aktualisierung (Sek.)" mit `min="10" max="300"
  step="10"` im Register „Monitor" von `templates/competition/show.php` —
  Eingabefeld statt Dropdown (30 Optionen wären unhandlich; das benachbarte
  Feld „Verweildauer je Block" nutzt dasselbe Muster).
- **Speichern:** `monitor_settings()` in `routes/competition.php` —
  `monitor_reload_clamp((int)post('monitor_reload', 60))`.
- **Anwenden:** `templates/competition/monitor.php` löst
  `$mon_reload = monitor_reload_clamp($ov_reload !== null ? (int)$ov_reload :
  (int)($c['monitor_reload'] ?? 60))` auf; im JS wird `PERIOD` aus dem Wert
  gesetzt (`<?= (int)$mon_reload * 1000 ?>` statt der Konstante 60000).
- **Query-Override:** Parameter `reload` hat — wie `zoom` — Vorrang vor dem
  Bewerbswert; `monitor()` reicht ihn als `$ov_reload` (roher `(int)` oder
  null) durch.
- **Watchdog unverändert:** der Sicherheits-Reload nach 10 min (600000 ms)
  bleibt fest — er liegt über dem Maximum von 300 s.
- **Hinweistext** im form-text-Block: Reload erfolgt frühestens nach Ablauf
  des Intervalls, jeweils am Ende des Scroll-Durchlaufs.

### 4. Turnier-Monitor

- **Einstellung:** gleiches Zahlenfeld im Monitor-Tab von
  `templates/tournament/show.php`; Speichern in `monitor_settings()` in
  `routes/tournament.php` (gleiche Validierung).
- **Anwenden:** `routes/tournament.php` `monitor()` übergibt
  `mon_reload` (geklemmt); `templates/tournament/monitor.php` reicht den Wert
  als Query-Parameter `reload` in `$embed_params` an die iframes weiter.

## Rechte & Audit

Keine Sonderbehandlung — die bestehenden Gates der beiden
`monitor_settings()`-Handler decken das neue POST-Feld automatisch ab.

## Testen

- Query-Override: `?reload=10` / `?reload=300` → JS enthält `PERIOD = 10000` /
  `300000`; `?reload=55` → 60000 (Rundung); `?reload=999` → 300000 (Klemmen);
  ohne Parameter → gespeicherter Wert (Default 60 → 60000).
- Speichern über die UI: Wert setzen, Monitor rendert das Intervall; Grenzwerte
  (9 → 10, 301 → 300, 55 → 60), Default 60 bei Bestandsdaten.
- Turnier-Monitor: iframe-URLs enthalten `reload=<wert>`.
- Verhalten: Reload weiterhin nur am Zyklusende (gleichmäßig: oben angekommen;
  blockweise: nach letztem Block; ohne Scrollbedarf: einfacher Timeout).
