#!/usr/bin/env bash
# HTTP-Test: Turnier anlegen/löschen — Sportart-Whitelist, Upload-Dateien werden mitgelöscht.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080, Dev-Admin. Aufruf: bash tests/tournament_admin_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
"$MYSQL" -u root turnierverwaltung -e "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"   # Test-Isolation: Login-Limit
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
C=$(curl -s -c "$JAR" $B/login | csrf)
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$C" $B/login
C=$(curl -s -b "$JAR" $B/ | csrf)

# 1) Unbekannte Sportart wird nicht gespeichert; bekannte schon
cp static/cornhole.png "$W/banner.png"
printf '%%PDF-1.4\n%%EOF\n' > "$W/aus.pdf"
curl -s -b "$JAR" -o /dev/null -F csrf_token="$C" -F name="E2E Sport ungueltig" --form-string "sport=<b>x</b>" \
  -F "banner_file=@$W/banner.png" -F "ausschreibung_file=@$W/aus.pdf" $B/tournament/new
TID=$(q "SELECT MAX(id) FROM tournament WHERE name='E2E Sport ungueltig'")
[ "$(q "SELECT sport FROM tournament WHERE id=$TID")" = "" ] && ok "unbekannte Sportart verworfen" || bad "unbekannte Sportart verworfen"
curl -s -b "$JAR" -o /dev/null -F csrf_token="$C" -F name="E2E Sport gueltig" -F sport=tennis $B/tournament/new
T2=$(q "SELECT MAX(id) FROM tournament WHERE name='E2E Sport gueltig'")
[ "$(q "SELECT sport FROM tournament WHERE id=$T2")" = "tennis" ] && ok "bekannte Sportart gespeichert" || bad "bekannte Sportart gespeichert"

# 2) Löschen entfernt Banner und Ausschreibung
BAN=$(q "SELECT banner_image FROM tournament WHERE id=$TID"); AUS=$(q "SELECT ausschreibung FROM tournament WHERE id=$TID")
[ -n "$BAN" ] && [ -f "uploads/$BAN" ] && [ -n "$AUS" ] && [ -f "uploads/$AUS" ] && ok "Uploads angelegt" || bad "Uploads angelegt ($BAN / $AUS)"
curl -s -b "$JAR" -o /dev/null --data-urlencode "csrf_token=$C" $B/tournament/$TID/delete
[ ! -f "uploads/$BAN" ] && [ ! -f "uploads/$AUS" ] && ok "Löschen entfernt Banner und Ausschreibung" || bad "Löschen entfernt Banner und Ausschreibung"

# 3) Bewerbe umsortieren auch bei beendetem Turnier (nur Bearbeiter), sonst bleibt es gesperrt
q "UPDATE tournament SET is_done=1, is_public=1 WHERE id=$T2"
q "INSERT INTO competition (tournament_id,name) VALUES ($T2,'A'),($T2,'B')"
PA=$(curl -s -b "$JAR" $B/tournament/$T2)
echo "$PA" | grep -q 'id="comp-sort-toggle"' && ok "beendet: Umsortieren für Admin" || bad "beendet: Umsortieren für Admin"
echo "$PA" | grep -q 'data-bs-target="#newCompetitionModal"' && bad "beendet: Neuer Bewerb bleibt gesperrt" || ok "beendet: Neuer Bewerb bleibt gesperrt"
curl -s $B/tournament/$T2 | grep -q 'id="comp-sort-toggle"' && bad "Gast: kein Umsortieren" || ok "Gast: kein Umsortieren"
IDS=$(q "SELECT GROUP_CONCAT(id ORDER BY id DESC) FROM competition WHERE tournament_id=$T2")
curl -s -b "$JAR" -o /dev/null --data-urlencode "csrf_token=$C" -d "ids[]=${IDS%%,*}" -d "ids[]=${IDS##*,}" $B/tournament/$T2/competitions/reorder
[ "$(q "SELECT GROUP_CONCAT(id ORDER BY sort_order) FROM competition WHERE tournament_id=$T2")" = "$IDS" ] && ok "beendet: Reihenfolge gespeichert" || bad "beendet: Reihenfolge gespeichert"

q "DELETE FROM tournament WHERE id IN ($TID,$T2)"; [ -n "$BAN" ] && rm -f "uploads/$BAN"; [ -n "$AUS" ] && rm -f "uploads/$AUS"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
