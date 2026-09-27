#!/usr/bin/env bash
# HTTP-Test „Mail an Teilnehmer“. Voraussetzung: MariaDB + PHP-Server auf localhost:8080,
# Dev-Benutzer dev-admin@local.test / devpass123. Aufruf: bash tests/tournament_mail_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
"$MYSQL" -u root turnierverwaltung -e "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"   # Test-Isolation: Login-Limit
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()   { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }

CSRF=$(curl -s -c "$JAR" $B/login | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$CSRF" $B/login

TID=$(q "INSERT INTO tournament (name,is_public) VALUES ('E2E Mail & Co',1); SELECT LAST_INSERT_ID();")
CID=$(q "INSERT INTO competition (tournament_id,name) VALUES ($TID,'Einzel'); SELECT LAST_INSERT_ID();")
PID=$(q "INSERT INTO player (name,email) VALUES ('E2E-Mailspieler','e2e-mail@test.at'); SELECT LAST_INSERT_ID();")
q "INSERT INTO competition_player (competition_id,player_id) VALUES ($CID,$PID)"

PA=$(curl -s -b "$JAR" $B/tournament/$TID); PG=$(curl -s $B/tournament/$TID)
echo "$PA" | grep -q 'mailto:?bcc=e2e-mail%40test.at' && ok "Admin: mailto mit BCC" || bad "Admin: mailto mit BCC"
echo "$PA" | grep -q 'subject=E2E%20Mail%20%26%20Co' && ok "Admin: Betreff = Turniername" || bad "Admin: Betreff = Turniername"
echo "$PA" | grep -q 'Mail an Teilnehmer (1)' && ok "Admin: Anzahl" || bad "Admin: Anzahl"
echo "$PG" | grep -q 'e2e-mail' && bad "Gast: keine Adressen im HTML" || ok "Gast: keine Adressen im HTML"
echo "$PG" | grep -qE 'mailto:|id="copy-participant-emails"' && bad "Gast: kein Link" || ok "Gast: kein Link"

q "DELETE FROM tournament WHERE id=$TID; DELETE FROM player WHERE id=$PID"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
