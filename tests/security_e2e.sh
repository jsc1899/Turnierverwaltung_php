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

# Seiten funktionieren weiterhin
for p in "" login hilfe; do
  R=$(curl -s -o /dev/null -w '%{http_code}' "$B/$p"); [ "$R" = 200 ] && ok "/$p → 200" || bad "/$p → $R"
done
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
