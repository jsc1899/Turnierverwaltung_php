#!/usr/bin/env bash
# HTTP-Test: Spielerprofil (JSON) zeigt Editoren keine fremden, nicht öffentlichen Turniere.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080, Dev-Konten. Aufruf: bash tests/player_profile_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
"$MYSQL" -u root turnierverwaltung -e "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"   # Test-Isolation: Login-Limit
W=$(mktemp -d); FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
login() { local c; c=$(curl -s -c "$1" $B/login | csrf)
  curl -s -b "$1" -c "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$c" $B/login; }

TID=$(q "INSERT INTO tournament (name,is_public) VALUES ('GEHEIM-Profiltest',0); SELECT LAST_INSERT_ID();")
PUB=$(q "INSERT INTO tournament (name,is_public) VALUES ('OEFFENTLICH-Profiltest',1); SELECT LAST_INSERT_ID();")
C1=$(q "INSERT INTO competition (tournament_id,name) VALUES ($TID,'Einzel'); SELECT LAST_INSERT_ID();")
C2=$(q "INSERT INTO competition (tournament_id,name) VALUES ($PUB,'Einzel'); SELECT LAST_INSERT_ID();")
PID=$(q "INSERT INTO player (name,firstname) VALUES ('Profilus','Paul'); SELECT LAST_INSERT_ID();")
q "INSERT INTO competition_player (competition_id,player_id) VALUES ($C1,$PID),($C2,$PID)"

login "$W/ed" dev-editor@local.test
J=$(curl -s -b "$W/ed" $B/player/$PID/profile)
echo "$J" | grep -q 'GEHEIM-Profiltest' && bad "Editor: fremdes nicht-öffentliches Turnier verborgen" || ok "Editor: fremdes nicht-öffentliches Turnier verborgen"
echo "$J" | grep -q 'OEFFENTLICH-Profiltest' && ok "Editor: öffentliches Turnier sichtbar" || bad "Editor: öffentliches Turnier sichtbar"
login "$W/ad" dev-admin@local.test
curl -s -b "$W/ad" $B/player/$PID/profile | grep -q 'GEHEIM-Profiltest' && ok "Admin: alle Turniere sichtbar" || bad "Admin: alle Turniere sichtbar"

q "DELETE FROM tournament WHERE id IN ($TID,$PUB); DELETE FROM player WHERE id=$PID"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
