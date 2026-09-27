#!/usr/bin/env bash
# HTTP-Test: Monitor-Reiter/-Links bei beendeten Turnieren ausgeblendet.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080, Dev-Admin. Aufruf: bash tests/monitor_done_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()   { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
has() { echo "$1" | grep -q "/monitor\""; }   # Link auf …/monitor

CSRF=$(curl -s -c "$JAR" $B/login | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$CSRF" $B/login

TID=$(q "INSERT INTO tournament (name,is_public,is_done) VALUES ('E2E Monitor',1,0); SELECT LAST_INSERT_ID();")
CID=$(q "INSERT INTO competition (tournament_id,name) VALUES ($TID,'Einzel'); SELECT LAST_INSERT_ID();")

for who in Gast Admin; do
  [ $who = Admin ] && C=(-b "$JAR") || C=()
  q "UPDATE tournament SET is_done=0 WHERE id=$TID"
  has "$(curl -s "${C[@]}" $B/tournament/$TID)"   && ok "$who offen: Turnier-Monitor sichtbar"  || bad "$who offen: Turnier-Monitor sichtbar"
  has "$(curl -s "${C[@]}" $B/competition/$CID)"  && ok "$who offen: Bewerbs-Monitor sichtbar"  || bad "$who offen: Bewerbs-Monitor sichtbar"
done
# Beendet: Gäste sehen keinen Monitor mehr, Admins/zugeordnete Editoren weiterhin
q "UPDATE tournament SET is_done=1 WHERE id=$TID"
PT=$(curl -s $B/tournament/$TID); PC=$(curl -s $B/competition/$CID)
has "$PT" && bad "Gast beendet: Turnier-Monitor ausgeblendet" || ok "Gast beendet: Turnier-Monitor ausgeblendet"
has "$PC" && bad "Gast beendet: Bewerbs-Monitor ausgeblendet" || ok "Gast beendet: Bewerbs-Monitor ausgeblendet"
PT=$(curl -s -b "$JAR" $B/tournament/$TID); PC=$(curl -s -b "$JAR" $B/competition/$CID)
has "$PT" && ok "Admin beendet: Turnier-Monitor sichtbar" || bad "Admin beendet: Turnier-Monitor sichtbar"
has "$PC" && ok "Admin beendet: Bewerbs-Monitor sichtbar" || bad "Admin beendet: Bewerbs-Monitor sichtbar"
echo "$PT" | grep -q 'id="tab-monitor"' && ok "Admin beendet: Monitor-Reiter Turnier" || bad "Admin beendet: Monitor-Reiter Turnier"
echo "$PC" | grep -q 'id="tab-monitor"' && ok "Admin beendet: Monitor-Reiter Bewerb" || bad "Admin beendet: Monitor-Reiter Bewerb"

q "DELETE FROM tournament WHERE id=$TID"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
