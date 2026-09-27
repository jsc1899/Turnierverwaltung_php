#!/usr/bin/env bash
# HTTP-Test: Änderungsanträge per Magic-Link nur für Bewerbe des eigenen Turniers; auch beim
# Bestätigen (Altanträge) wird nie in einen fremden Bewerb zugeteilt.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080 (unerreichbarer MAIL_HOST), Dev-Admin.
# Aufruf: bash tests/registration_scope_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
W=$(mktemp -d); JAR="$W/jar"; AJ="$W/admin"; FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
MAIL='scope.e2e@beispiel.at'

T1=$(q "INSERT INTO tournament (name,is_public,registrations_open,max_competitions) VALUES ('E2E Scope eigen',1,1,5); SELECT LAST_INSERT_ID();")
T2=$(q "INSERT INTO tournament (name,is_public,registrations_open) VALUES ('E2E Scope fremd',0,1); SELECT LAST_INSERT_ID();")
C1=$(q "INSERT INTO competition (tournament_id,name,registrations_open) VALUES ($T1,'Eigen offen',1); SELECT LAST_INSERT_ID();")
C2=$(q "INSERT INTO competition (tournament_id,name,registrations_open) VALUES ($T2,'Fremd',1); SELECT LAST_INSERT_ID();")
RID=$(q "INSERT INTO registration (tournament_id,lastname,firstname,email,status) VALUES ($T1,'Scopius','Sam','$MAIL','confirmed'); SELECT LAST_INSERT_ID();")
TOK=$(php -r 'require "config.php"; require "lib/tokens.php"; echo make_manage_email_token($argv[1]);' -- "$MAIL")
CSRF=$(curl -s -c "$JAR" "$B/nennung/verwalten/$TOK" | csrf)
[ -n "$CSRF" ] && ok "Magic-Link-Seite: CSRF-Token" || bad "Magic-Link-Seite: CSRF-Token"
cids() { q "SELECT COALESCE(GROUP_CONCAT(rcc.competition_id),'') FROM registration_change_competition rcc JOIN registration_change_request r ON r.id=rcc.change_request_id WHERE r.registration_id=$RID"; }

# 1) Antrag mit eigenem offenem + fremdem Bewerb: nur der eigene wird gespeichert
curl -s -b "$JAR" -o /dev/null --data-urlencode "csrf_token=$CSRF" \
  -d "competition_ids[]=$C1" -d "competition_ids[]=$C2" "$B/nennung/verwalten/$TOK/change/$RID"
CIDS=$(cids)
echo ",$CIDS," | grep -q ",$C1," && ok "eigener offener Bewerb beantragt" || bad "eigener offener Bewerb beantragt ($CIDS)"
echo ",$CIDS," | grep -q ",$C2," && bad "fremder Bewerb abgewiesen ($CIDS)" || ok "fremder Bewerb abgewiesen"

# 2) Nur fremder Bewerb: nichts gespeichert
q "DELETE FROM registration_change_request WHERE registration_id=$RID"
curl -s -b "$JAR" -o /dev/null --data-urlencode "csrf_token=$CSRF" -d "competition_ids[]=$C2" "$B/nennung/verwalten/$TOK/change/$RID"
echo ",$(cids)," | grep -q ",$C2," && bad "nur fremder Bewerb: nichts gespeichert" || ok "nur fremder Bewerb: nichts gespeichert"

# 3) Altantrag mit fremdem Bewerb bestätigen (einzeln und gesamt): keine Zuteilung
AC=$(curl -s -c "$AJ" $B/login | csrf)
curl -s -b "$AJ" -c "$AJ" -o /dev/null --data-urlencode "email=dev-admin@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$AC" $B/login
AC=$(curl -s -b "$AJ" $B/tournament/$T1 | csrf)
for MODE in comp all; do
  q "DELETE FROM registration_change_request WHERE registration_id=$RID"
  RCR=$(q "INSERT INTO registration_change_request (registration_id,request_type) VALUES ($RID,'change'); SELECT LAST_INSERT_ID();")
  q "INSERT INTO registration_change_competition (change_request_id,competition_id,action) VALUES ($RCR,$C2,'add')"
  if [ $MODE = comp ]; then U="$B/reg-change/$RCR/comp/$C2/confirm"; else U="$B/reg-change/$RCR/confirm"; fi
  HC=$(curl -s -b "$AJ" -o /dev/null -w '%{http_code}' --data-urlencode "csrf_token=$AC" "$U")
  [ "$HC" = 302 ] && ok "Altantrag ($MODE): Anfrage angenommen" || bad "Altantrag ($MODE): Anfrage angenommen (HTTP $HC)"
  N=$(q "SELECT COUNT(*) FROM competition_player WHERE competition_id=$C2")
  [ "$N" = 0 ] && ok "Altantrag ($MODE): fremder Bewerb nicht zugeteilt" || bad "Altantrag ($MODE): fremder Bewerb nicht zugeteilt"
done

q "DELETE FROM tournament WHERE id IN ($T1,$T2); DELETE FROM player WHERE name='Scopius' AND firstname='Sam'"
rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
