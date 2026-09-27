#!/usr/bin/env bash
# HTTP-Test der Galerie. Voraussetzung: MariaDB + PHP-Server auf localhost:8080,
# Dev-Benutzer dev-admin@local.test / devpass123. Aufruf: bash tests/gallery_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
expect() { [ "$2" = "$3" ] && ok "$1" || bad "$1 (erwartet $3, bekommen $2)"; }

CSRF=$(curl -s -c "$JAR" $B/login | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$CSRF" $B/login

TID=$("$MYSQL" -u root turnierverwaltung -N -e "INSERT INTO tournament (name,is_public) VALUES ('E2E-Galerie',0); SELECT LAST_INSERT_ID();")
CSRF=$(curl -s -b "$JAR" $B/tournament/$TID | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')

# Testvideo: 2,5 MB mit gültigem MP4-Header (ftyp) → 3 Teile
{ printf '\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom'; head -c 2621440 /dev/zero; } > "$W/clip.mp4"
SIZE=$(stat -c %s "$W/clip.mp4"); CH=1048576; TOTAL=$(( (SIZE + CH - 1) / CH ))
UID_=$(printf 'e%.0s' {1..32})
for ((i=0;i<TOTAL;i++)); do
  dd if="$W/clip.mp4" of="$W/part" bs=$CH skip=$i count=1 2>/dev/null
  R=$(curl -s -b "$JAR" -F csrf_token="$CSRF" -F upload_id=$UID_ -F index=$i -F total=$TOTAL \
      -F name=clip.mp4 -F size=$SIZE -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)
done
echo "$R" | grep -q '"done":true' && ok "Video hochgeladen" || bad "Video-Upload: $R"
GID=$(echo "$R" | grep -oE '"id":[0-9]+' | grep -oE '[0-9]+')

expect "Admin: media 200"         "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/gallery/$GID/media)" 200
expect "Range 206"                "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -H 'Range: bytes=0-99' $B/gallery/$GID/media)" 206
CR=$(curl -s -D - -o /dev/null -b "$JAR" -H 'Range: bytes=0-99' $B/gallery/$GID/media | grep -i '^content-range' | tr -d '\r')
expect "Content-Range"            "$CR" "Content-Range: bytes 0-99/$SIZE"
expect "Range ungültig 416"       "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -H "Range: bytes=$SIZE-" $B/gallery/$GID/media)" 416
expect "Gast, nicht öffentlich 404" "$(curl -s -o /dev/null -w '%{http_code}' $B/gallery/$GID/media)" 404
"$MYSQL" -u root turnierverwaltung -e "UPDATE tournament SET is_public=1 WHERE id=$TID"
expect "Gast, öffentlich 200"     "$(curl -s -o /dev/null -w '%{http_code}' $B/gallery/$GID/media)" 200
expect "Gast Upload verweigert"   "$(curl -s -o /dev/null -w '%{http_code}' -F csrf_token=x -F upload_id=$UID_ -F index=0 -F total=1 -F name=a.jpg -F size=1 -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)" 302
R=$(curl -s -b "$JAR" -F csrf_token="$CSRF" -F upload_id=$UID_ -F index=0 -F total=1 -F name=a.exe -F size=10 -F chunk=@"$W/part" $B/tournament/$TID/gallery/chunk)
echo "$R" | grep -q 'Dateityp nicht erlaubt' && ok "Endung abgelehnt" || bad "Endung: $R"

curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" --data-urlencode "caption=Finale 2026" $B/gallery/$GID/caption
expect "Beschriftung gespeichert" "$("$MYSQL" -u root turnierverwaltung -N -e "SELECT caption FROM gallery_item WHERE id=$GID")" "Finale 2026"
# Nur der letzte Teil des Video-Uploads wird protokolliert (Target beginnt mit dem Dateinamen)
N=$("$MYSQL" -u root turnierverwaltung -N -e "SELECT COUNT(*) FROM audit_log WHERE action='gallery.upload_chunk' AND path LIKE '%/tournament/$TID/%' AND target LIKE 'clip.mp4%'")
expect "Audit: 1 Eintrag je Upload" "$N" 1

# Reiter „Galerie“: Gast sieht ihn bei vorhandenen Medien (ohne Upload), Admin mit Upload
PG=$(curl -s $B/tournament/$TID); PA=$(curl -s -b "$JAR" $B/tournament/$TID)
echo "$PG" | grep -q 'id="tab-gallery-btn"' && ok "Gast: Reiter sichtbar" || bad "Gast: Reiter sichtbar"
echo "$PG" | grep -q 'id="gallery-upload"' && bad "Gast: kein Upload" || ok "Gast: kein Upload"
echo "$PG" | grep -q "gallery/$GID/media" && ok "Gast: Medium verlinkt" || bad "Gast: Medium verlinkt"
echo "$PA" | grep -q 'id="gallery-upload"' && ok "Admin: Upload-Bereich" || bad "Admin: Upload-Bereich"
curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" $B/gallery/$GID/delete
PG=$(curl -s $B/tournament/$TID)
echo "$PG" | grep -q 'id="tab-gallery-btn"' && bad "Gast: ohne Medien kein Reiter" || ok "Gast: ohne Medien kein Reiter"
expect "gelöscht → 404"           "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/gallery/$GID/media)" 404

"$MYSQL" -u root turnierverwaltung -e "DELETE FROM tournament WHERE id=$TID"
rm -rf "$(dirname "$0")/../uploads/gallery/$TID"
# Turnier-Löschung über die App entfernt das Galerie-Verzeichnis
TID2=$("$MYSQL" -u root turnierverwaltung -N -e "INSERT INTO tournament (name) VALUES ('E2E-Del'); SELECT LAST_INSERT_ID();")
D2="$(dirname "$0")/../uploads/gallery/$TID2"; mkdir -p "$D2" && touch "$D2/x.jpg"
curl -s -o /dev/null -b "$JAR" --data-urlencode "csrf_token=$CSRF" $B/tournament/$TID2/delete
[ -d "$D2" ] && bad "Turnier-Löschung entfernt Galerie-Verzeichnis" || ok "Turnier-Löschung entfernt Galerie-Verzeichnis"
rm -rf "$D2"

rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
