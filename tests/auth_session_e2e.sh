#!/usr/bin/env bash
# HTTP-Test: Sitzungen/Konten — Deaktivierung wirkt sofort, alte Bestätigungslinks reaktivieren
# nicht, Passwortänderung beendet andere Sitzungen, ADMIN_EMAIL-Registrierung braucht Bestätigung.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080 (unerreichbarer MAIL_HOST), Dev-Konten.
# Aufruf: bash tests/auth_session_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
W=$(mktemp -d); FAILS=0
ok()   { echo "  ok   $1"; }
bad()  { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()    { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
csrf() { grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
login() {  # login <jar> <email>
  local c; c=$(curl -s -c "$1" $B/login | csrf)
  curl -s -b "$1" -c "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=devpass123" \
    --data-urlencode "csrf_token=$c" $B/login
}
code() { curl -s -o /dev/null -w '%{http_code}' -b "$1" "$2"; }   # code <jar> <url>

EID=$(q "SELECT id FROM user WHERE email='dev-editor@local.test'")
EHASH=$(q "SELECT password_hash FROM user WHERE id=$EID")
restore() { q "UPDATE user SET confirmed=1, password_hash='$EHASH' WHERE id=$EID"; q "UPDATE user SET deactivated=0 WHERE id=$EID" 2>/dev/null; }
trap restore EXIT

# 1) Deaktivierung durch Admin beendet laufende Editor-Sitzung sofort
login "$W/ed" dev-editor@local.test
[ "$(code "$W/ed" $B/players)" = 200 ] && ok "Editor angemeldet: /players 200" || bad "Editor angemeldet: /players 200"
login "$W/ad" dev-admin@local.test
AC=$(curl -s -b "$W/ad" $B/admin/users | csrf)
curl -s -b "$W/ad" -o /dev/null --data-urlencode "csrf_token=$AC" $B/admin/user/$EID/active
[ "$(code "$W/ed" $B/players)" != 200 ] && ok "deaktiviert: Sitzung sofort ungültig" || bad "deaktiviert: Sitzung sofort ungültig"

# 2) Alter (noch gültiger) Bestätigungslink reaktiviert ein deaktiviertes Konto nicht
T=$(php -r 'require "config.php"; require "lib/tokens.php"; echo make_email_confirm_token("dev-editor@local.test");')
curl -s -o /dev/null "$B/confirm?token=$T"
login "$W/ed2" dev-editor@local.test
[ "$(code "$W/ed2" $B/players)" != 200 ] && ok "Bestätigungslink reaktiviert nicht" || bad "Bestätigungslink reaktiviert nicht"

# Wieder aktivieren (über die App)
curl -s -b "$W/ad" -o /dev/null --data-urlencode "csrf_token=$AC" $B/admin/user/$EID/active
login "$W/ed3" dev-editor@local.test
[ "$(code "$W/ed3" $B/players)" = 200 ] && ok "reaktiviert durch Admin: Anmeldung möglich" || bad "reaktiviert durch Admin: Anmeldung möglich"

# 3) Passwortänderung per Reset-Link beendet andere Sitzungen
RT=$(php -r 'require "config.php"; require "lib/tokens.php"; echo make_reset_token($argv[1], $argv[2]);' -- dev-editor@local.test "$EHASH")
RC=$(curl -s -c "$W/rs" "$B/reset-password?token=$RT" | csrf)
curl -s -b "$W/rs" -o /dev/null --data-urlencode "csrf_token=$RC" --data-urlencode "password=neuespasswort1" \
  --data-urlencode "password2=neuespasswort1" "$B/reset-password?token=$RT"
[ "$(q "SELECT password_hash<>'$EHASH' FROM user WHERE id=$EID")" = 1 ] && ok "Reset: Passwort geändert" || bad "Reset: Passwort geändert"
[ "$(code "$W/ed3" $B/players)" != 200 ] && ok "Reset: alte Sitzung beendet" || bad "Reset: alte Sitzung beendet"
restore

# 4) Registrierung mit ADMIN_EMAIL (Konto fehlt) wird NICHT automatisch bestätigt
AE=$(php -r 'require "config.php"; echo ADMIN_EMAIL;')
AID=$(q "SELECT id FROM user WHERE LOWER(email)=LOWER('$AE')")
[ -n "$AID" ] && q "UPDATE user SET email='tmp-admin-e2e@local.test' WHERE id=$AID"
RG=$(curl -s -c "$W/rg" $B/register | csrf)
curl -s -b "$W/rg" -o /dev/null --data-urlencode "csrf_token=$RG" --data-urlencode "username=e2e-adminfake" \
  --data-urlencode "email=$AE" --data-urlencode "password=irgendwas123" --data-urlencode "password2=irgendwas123" $B/register
CF=$(q "SELECT confirmed FROM user WHERE username='e2e-adminfake'")
q "DELETE FROM user WHERE username='e2e-adminfake'"
[ -n "$AID" ] && q "UPDATE user SET email='$AE' WHERE id=$AID"
[ "$CF" = 0 ] && ok "ADMIN_EMAIL-Registrierung: Bestätigung nötig" || bad "ADMIN_EMAIL-Registrierung: Bestätigung nötig (confirmed=$CF)"

rm -rf "$W"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
