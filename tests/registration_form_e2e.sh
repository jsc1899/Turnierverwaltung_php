#!/usr/bin/env bash
# HTTP-Test: öffentliches Nennungsformular — gültige E-Mail, Honeypot, Rate-Limit.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080 (unerreichbarer MAIL_HOST!).
# Aufruf: bash tests/registration_form_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
W=$(mktemp -d); JAR="$W/jar"; FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
q "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"

TID=$(q "INSERT INTO tournament (name,is_public,registrations_open,max_competitions) VALUES ('E2E Formular',1,1,3); SELECT LAST_INSERT_ID();")
CID=$(q "INSERT INTO competition (tournament_id,name,registrations_open) VALUES ($TID,'Einzel',1); SELECT LAST_INSERT_ID();")
CSRF=$(curl -s -c "$JAR" "$B/tournament/$TID/register" | csrf)
nenne() {  # nenne <nachname> <email> [honeypot]
  curl -s -b "$JAR" -o /dev/null --data-urlencode "csrf_token=$CSRF" --data-urlencode "lastname=$1" \
    --data-urlencode "firstname=Test" --data-urlencode "email=$2" --data-urlencode "skill=1500" \
    --data-urlencode "gender=m" -d "competition_ids[]=$CID" ${3:+--data-urlencode "website=$3"} "$B/tournament/$TID/register"
}
cnt() { q "SELECT COUNT(*) FROM registration WHERE tournament_id=$TID${1:+ AND lastname='$1'}"; }

nenne Gueltig gueltig@beispiel.at
[ "$(cnt Gueltig)" = 1 ] && ok "gültige Nennung gespeichert" || bad "gültige Nennung gespeichert"
nenne Ungueltig 'keine-adresse'
[ "$(cnt Ungueltig)" = 0 ] && ok "ungültige E-Mail abgelehnt" || bad "ungültige E-Mail abgelehnt"
nenne Bot bot@beispiel.at "http://spam.example"
[ "$(cnt Bot)" = 0 ] && ok "Honeypot: nichts gespeichert" || bad "Honeypot: nichts gespeichert"

# Rate-Limit: max. 20 Nennungen je 10 Minuten und IP
for i in $(seq 1 24); do nenne "Flut$i" "flut$i@beispiel.at"; done
N=$(q "SELECT COUNT(*) FROM registration WHERE tournament_id=$TID AND lastname LIKE 'Flut%'")
[ "$N" -le 20 ] && [ "$N" -ge 15 ] && ok "Rate-Limit greift ($N von 24 gespeichert)" || bad "Rate-Limit greift ($N von 24 gespeichert)"

q "DELETE FROM tournament WHERE id=$TID; DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
