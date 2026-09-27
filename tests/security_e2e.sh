#!/usr/bin/env bash
# HTTP-Tests zur Härtung: interne PHP-Dateien nicht direkt ausführbar, Header.
# Voraussetzung: PHP-Server auf localhost:8080. Aufruf: bash tests/security_e2e.sh
set -u
B=http://localhost:8080
cd "$(dirname "$0")/.."
FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }

# Interne PHP-Dateien: bei Direktaufruf 404 ohne Inhalt (unter nginx sonst direkt ausführbar)
FILES="config.php db.php helpers.php auth.php $(ls lib/*.php routes/*.php) $(find templates -name '*.php' | sort)"
for f in $FILES; do
  R=$(curl -s -o "$TEMP/sec_body" -w '%{http_code}' "$B/$f"); N=$(wc -c < "$TEMP/sec_body")
  if [ "$R" = 404 ] && [ "$N" -eq 0 ]; then :; else bad "Direktaufruf /$f → $R, $N Byte"; fi
done
[ $FAILS -eq 0 ] && ok "Direktaufruf interner PHP-Dateien: alle 404 ohne Inhalt"

H=$(curl -s -D - -o /dev/null $B/ | tr -d '\r')
echo "$H" | grep -qi '^x-powered-by:' && bad "kein X-Powered-By" || ok "kein X-Powered-By"
CSP=$(echo "$H" | grep -i '^content-security-policy:')
for d in "object-src 'none'" "base-uri 'self'" "form-action 'self'" "frame-ancestors 'self'"; do
  echo "$CSP" | grep -q "$d" && ok "CSP: $d" || bad "CSP: $d"
done
echo "$CSP" | grep -qE "https://cdn.jsdelivr.net( |;)" && bad "CSP: jsdelivr nur konkrete Pakete" || ok "CSP: jsdelivr nur konkrete Pakete"

# Zustandsändernde Aktionen nicht per GET (CSRF): Logout und Spielstärke-Sync
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
"$MYSQL" -u root turnierverwaltung -e "DELETE FROM rate_limit WHERE ip IN ('::1','127.0.0.1')"
JAR=$(mktemp)
C=$(curl -s -c "$JAR" $B/login | grep -oE 'name="csrf_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=dev-editor@local.test" \
  --data-urlencode "password=devpass123" --data-urlencode "csrf_token=$C" $B/login
curl -s -b "$JAR" -c "$JAR" -o /dev/null $B/logout
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/players)" = 200 ] && ok "GET /logout meldet nicht ab" || bad "GET /logout meldet nicht ab"
PID=$("$MYSQL" -u root turnierverwaltung -N -e "SELECT MIN(id) FROM player")
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/player/$PID/sync/ratingscentral)" = 404 ] && ok "GET Sync nicht möglich" || bad "GET Sync nicht möglich"
C=$(curl -s -b "$JAR" $B/players | grep -oE 'name="csrf-token" content="[^"]+"' | head -1 | sed -E 's/.*content="([^"]+)".*/\1/')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$C" $B/logout
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" $B/players)" != 200 ] && ok "POST /logout mit Token meldet ab" || bad "POST /logout mit Token meldet ab"
rm -f "$JAR"

# Seiten funktionieren weiterhin
for p in "" login hilfe; do
  R=$(curl -s -o /dev/null -w '%{http_code}' "$B/$p"); [ "$R" = 200 ] && ok "/$p → 200" || bad "/$p → $R"
done
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
