# Turnier-Galerie (Fotos & Videos) — Design

Datum: 2026-09-27

## Ziel

Pro Turnier eine Galerie mit Fotos und Videos, die öffentlich angezeigt wird und auf der
Turnierseite verlinkt ist.

## Anforderungen (abgestimmt)

- **Hochladen/Beschriften/Löschen**: nur Admins und dem Turnier zugeordnete Editoren
  (`can_edit_tournament($tid)`).
- **Sichtbarkeit**: wie das Turnier — `tournament.is_public = 1` → Galerie öffentlich; sonst nur
  für Bearbeiter (`is_public || can_edit_tournament`). Keine eigene Option.
- **Videos**: nur Datei-Upload (keine YouTube-/Vimeo-Links).
- **Upload-Art**: Chunk-Upload per JavaScript, unabhängig vom PHP-Upload-Limit des Hostings.

## Nicht im Umfang (YAGNI)

Alben, manuelles Umsortieren (Reihenfolge = neueste zuerst), Uploads durch Besucher,
Video-Vorschaubilder via ffmpeg, externe Video-Links.

## Datenmodell

Neue Tabelle in `init_db()` (`CREATE TABLE IF NOT EXISTS`):

| Spalte | Typ | Zweck |
|---|---|---|
| `id` | INT PK AI | |
| `tournament_id` | INT, FK → `tournament(id)` ON DELETE CASCADE | |
| `type` | ENUM/VARCHAR `'image'`/`'video'` | |
| `filename` | VARCHAR | zufälliger Dateiname (32 Hex + Endung) |
| `thumb` | VARCHAR NULL | Vorschaubild (nur Fotos) |
| `mime` | VARCHAR | per `finfo` ermittelter MIME-Typ (für Auslieferung) |
| `original_name` | VARCHAR | ursprünglicher Dateiname (Anzeige/Alt-Text) |
| `caption` | VARCHAR(255) NULL | Beschriftung |
| `size` | BIGINT | Dateigröße in Bytes |
| `uploaded_by` | INT NULL | Benutzer-ID (Snapshot, kein FK) |
| `created_at` | DATETIME | |

## Speicherung

- Dateien unter `UPLOAD_DIR . 'gallery/{tid}/'`, Chunks temporär unter
  `UPLOAD_DIR . 'gallery/_tmp/{upload_id}/'`.
- `uploads/gallery/.htaccess` mit `Require all denied` → kein Direktzugriff (Apache/Live).
  Lokal (`router.php`) wird `uploads/gallery/` ebenfalls nicht statisch ausgeliefert, sondern an
  `index.php` weitergereicht (404).
- Löschen eines Mediums entfernt Datei + Vorschaubild. Löschen eines Turniers
  (`tournament.php delete()`) entfernt das Verzeichnis `gallery/{tid}/` rekursiv (die DB-Zeilen
  fallen per CASCADE).
- Verwaiste Chunk-Verzeichnisse älter als 24 h werden beim nächsten Upload-Start aufgeräumt.

## Neues Modul `routes/gallery.php` + `lib/gallery.php`

Routen (in `index.php`):

| Methode | Pfad | Action | Recht |
|---|---|---|---|
| POST | `/tournament/{id}/gallery/chunk` | `upload_chunk` | `require_tournament_edit` |
| POST | `/gallery/{gid}/caption` | `caption` | `require_tournament_edit` (Turnier des Mediums) |
| POST | `/gallery/{gid}/delete` | `delete` | `require_tournament_edit` |
| GET | `/gallery/{gid}/file` | `file` | öffentlich bzw. Bearbeiter |
| GET | `/gallery/{gid}/thumb` | `thumb` | öffentlich bzw. Bearbeiter |

### Chunk-Upload-Protokoll

- JS zerlegt jede Datei in 2-MB-Stücke und sendet sie sequenziell als `multipart/form-data`:
  `csrf_token`, `upload_id` (32 Hex, clientseitig per `crypto.getRandomValues`), `index`
  (0-basiert), `total`, `name` (Originalname), `size` (Gesamtgröße), `chunk` (Blob).
- Server validiert: `upload_id` Regex `^[a-f0-9]{32}$`, `index < total`, Chunk ≤ 2 MB + Toleranz,
  deklarierte Gesamtgröße ≤ Maximalgröße, Endung in Whitelist.
- Chunk wird als `{index}.part` abgelegt. Beim letzten Chunk (alle `total` Teile vorhanden):
  Zusammensetzen zu einer Datei, reale Größe prüfen, `finfo` MIME prüfen:
  - Foto: `image/jpeg`, `image/png`, `image/webp`, `image/gif` — max. 25 MB
  - Video: `video/mp4`, `video/webm`, `video/quicktime` — max. 500 MB
  - Maximalgrößen als Konstanten in `config.php` (`GALLERY_MAX_IMAGE_MB`,
    `GALLERY_MAX_VIDEO_MB`), per ENV überschreibbar.
- Fotos (GD): EXIF-Orientierung anwenden (JPEG), auf max. 2560 px lange Kante verkleinern
  (nur falls größer), Vorschaubild 400 px (JPEG). GIF bleibt unverändert (Animation), bekommt
  aber ein statisches Vorschaubild.
- Antwort JSON: `{ok:true, done:false}` bzw. `{ok:true, done:true, id:…}` oder
  `{ok:false, error:"…"}` (deutsche Meldung). Fehler → Temp-Verzeichnis löschen.

### Auslieferung (`file`/`thumb`)

- Zugriffsprüfung: Turnier `is_public = 1` oder `can_edit_tournament($tid)`, sonst 404.
- Header: `Content-Type` = gespeicherter MIME, `X-Content-Type-Options: nosniff`,
  `Cache-Control: public, max-age=…` (öffentlich) bzw. `private`, `Accept-Ranges: bytes`.
- Range-Requests (`bytes=a-b`, `bytes=a-`, `bytes=-n`) mit `206 Partial Content` für Video-Seeking;
  Streaming in Blöcken mit `fread` (kein vollständiges Einlesen in den Speicher).
- Session vor dem Streaming schließen (`session_write_close()`), damit parallele Requests nicht
  blockieren.

## UI (Turnierseite `templates/tournament/show.php`)

- Neuer Reiter **„Galerie (n)“** — sichtbar, wenn Medien vorhanden sind oder der Benutzer
  bearbeiten darf.
- Kachelraster (responsive, quadratische Kacheln, `object-fit: cover`): Fotos über `thumb`,
  Videos als `<video preload="metadata">` mit Play-Symbol-Overlay.
- Klick → Bootstrap-Modal-Lightbox mit Vor/Zurück (Buttons + Pfeiltasten), Beschriftung, Videos
  mit `<video controls>`; Link „Original öffnen“.
- Bearbeiter zusätzlich: Upload-Bereich (Dateiauswahl, Mehrfachauswahl, Drag & Drop) mit
  Fortschrittsbalken je Datei, Seite lädt nach Abschluss aller Uploads neu; je Kachel
  „Beschriftung ändern“ (Inline-Formular) und „Löschen“ (Formular mit JS-`confirm`, wie im
  Rest der App üblich).
- Der Handler `show()` lädt die Medienliste und übergibt sie ans Template.

## Sicherheit

- CSRF-Prüfung bei allen POSTs (`csrf_verify()`; beim Chunk-Upload per FormData-Feld).
- Zugriffsschutz auf Medien über die PHP-Auslieferung (auch nicht öffentliche Turniere geschützt).
- Zufällige Dateinamen, keine Nutzereingabe im Pfad; MIME-Whitelist per `finfo`; Endung aus
  dem geprüften MIME abgeleitet.
- Audit-Log: automatisch über die Gates (`gallery.upload_chunk` usw.). Das Chunk-Logging
  erzeugt je Chunk einen Eintrag — daher wird im Audit-Log für `gallery.upload_chunk` nur der
  **letzte** Chunk protokolliert (bzw. Target = Originalname).

## Test

- Lokales Testskript: Chunk-Upload eines Fotos und eines Videos über HTTP (curl) als Editor,
  Zusammensetzen + Vorschaubild prüfen, Range-Request (`206`), Zugriff als Gast bei
  nicht-öffentlichem Turnier (404), Löschen entfernt Dateien, Turnier-Löschung entfernt
  Verzeichnis, ungültiger MIME wird abgelehnt.
- Browser-Test der Upload-UI und Lightbox.
