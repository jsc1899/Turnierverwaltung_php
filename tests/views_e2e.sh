#!/usr/bin/env bash
# HTTP-Test Zugriffszähler (Turnier, Bewerb, Galerie-Reiter, Galerie-Medium).
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080, Dev-Konten. Aufruf: bash tests/views_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
"$MYSQL" -u root turnierverwaltung -e "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"   # Test-Isolation: Login-Limit
W=$(mktemp -d); FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
login() { local c; c=$(curl -s -c "$1" $B/login | csrf)
  curl -s -b "$1" -c "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$c" $B/login; }
UA1='Mozilla/5.0 (Windows NT 10.0) Chrome/130 E2E-Besucher-1'
UA2='Mozilla/5.0 (Windows NT 10.0) Chrome/130 E2E-Besucher-2'
cnt() { q "SELECT COUNT(*) FROM view_log WHERE object_type='$1' AND object_id=$2"; }

TID=$(q "INSERT INTO tournament (name,is_public) VALUES ('E2E Zaehler',1); SELECT LAST_INSERT_ID();")
CID=$(q "INSERT INTO competition (tournament_id,name) VALUES ($TID,'Einzel'); SELECT LAST_INSERT_ID();")
q "INSERT INTO tournament_editor (tournament_id,user_id) SELECT $TID,id FROM user WHERE email='dev-editor@local.test'"
GID=$(q "INSERT INTO gallery_item (tournament_id,type,filename,mime,size) VALUES ($TID,'image','x.jpg','image/jpeg',1); SELECT LAST_INSERT_ID();")

# Turnier- und Bewerbsseite: Gast zählt einmal pro Tag, Neuladen nicht, zweiter Besucher schon
curl -s -A "$UA1" -o /dev/null $B/tournament/$TID; curl -s -A "$UA1" -o /dev/null $B/tournament/$TID
curl -s -A "$UA2" -o /dev/null $B/tournament/$TID
[ "$(cnt tournament $TID)" = 2 ] && ok "Turnier: 2 Besucher, Neuladen zählt nicht" || bad "Turnier: 2 Besucher ($(cnt tournament $TID))"
curl -s -A "$UA1" -o /dev/null $B/competition/$CID
[ "$(cnt competition $CID)" = 1 ] && ok "Bewerb: gezählt" || bad "Bewerb: gezählt"
curl -s -A "$UA1" -o /dev/null $B/competition/$CID/monitor
[ "$(cnt competition $CID)" = 1 ] && ok "Monitor zählt nicht" || bad "Monitor zählt nicht"
# Bot zählt nicht
curl -s -A "Mozilla/5.0 (compatible; Googlebot/2.1)" -o /dev/null $B/competition/$CID
[ "$(cnt competition $CID)" = 1 ] && ok "Bot zählt nicht" || bad "Bot zählt nicht"

# Galerie-Reiter und Medium: per POST-Meldung (Beacon)
curl -s -A "$UA1" -o /dev/null -X POST $B/tournament/$TID/gallery/view
curl -s -A "$UA1" -o /dev/null -X POST $B/gallery/$GID/view
[ "$(cnt gallery_tab $TID)" = 1 ] && ok "Galerie-Reiter gezählt" || bad "Galerie-Reiter gezählt"
[ "$(cnt gallery $GID)" = 1 ] && ok "Galerie-Medium gezählt" || bad "Galerie-Medium gezählt"

# Nicht öffentlich: Gast wird nicht gezählt
q "UPDATE tournament SET is_public=0 WHERE id=$TID"
curl -s -A "$UA2" -o /dev/null -X POST $B/gallery/$GID/view
curl -s -A "$UA2" -o /dev/null -X POST $B/tournament/$TID/gallery/view
[ "$(cnt gallery $GID)" = 1 ] && [ "$(cnt gallery_tab $TID)" = 1 ] && ok "nicht öffentlich: kein Zählen" || bad "nicht öffentlich: kein Zählen"
q "UPDATE tournament SET is_public=1 WHERE id=$TID"

# Editor zählt nicht, sieht aber die Zähler; Gast sieht sie nicht
login "$W/ed" dev-editor@local.test
PE=$(curl -s -A "$UA1" -b "$W/ed" $B/tournament/$TID); PC=$(curl -s -A "$UA1" -b "$W/ed" $B/competition/$CID)
[ "$(cnt tournament $TID)" = 2 ] && ok "Editor zählt nicht" || bad "Editor zählt nicht"
echo "$PE" | grep -q '2 Aufrufe' && ok "Editor: Turnier-Zähler sichtbar" || bad "Editor: Turnier-Zähler sichtbar"
echo "$PC" | grep -q '1 Aufruf ' && ok "Editor: Bewerbs-Zähler sichtbar" || bad "Editor: Bewerbs-Zähler sichtbar"
echo "$PE" | grep -q "data-views-gallery=\"1\"" && ok "Editor: Medien-Zähler sichtbar" || bad "Editor: Medien-Zähler sichtbar"
echo "$PE" | grep -q 'id="gallery-tab-views"' && ok "Editor: Galerie-Reiter-Zähler sichtbar" || bad "Editor: Galerie-Reiter-Zähler sichtbar"
PG=$(curl -s -A "$UA1" $B/tournament/$TID)
echo "$PG" | grep -qE 'Aufrufe|data-views-gallery|gallery-tab-views' && bad "Gast: keine Zähler sichtbar" || ok "Gast: keine Zähler sichtbar"

# Löschen des Turniers entfernt die Zählerdaten
q "DELETE FROM tournament_editor WHERE tournament_id=$TID"
AJ="$W/ad"; login "$AJ" dev-admin@local.test; AC=$(curl -s -b "$AJ" $B/tournament/$TID | csrf)
curl -s -b "$AJ" -o /dev/null --data-urlencode "csrf_token=$AC" $B/tournament/$TID/delete
N=$(q "SELECT COUNT(*) FROM view_log WHERE (object_type IN ('tournament','gallery_tab') AND object_id=$TID) OR (object_type='competition' AND object_id=$CID) OR (object_type='gallery' AND object_id=$GID)")
[ "$N" = 0 ] && ok "Turnier löschen entfernt Zählerdaten" || bad "Turnier löschen entfernt Zählerdaten ($N)"

q "DELETE FROM tournament WHERE id=$TID"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
