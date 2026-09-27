#!/usr/bin/env bash
# HTTP-Test: E-Mail-Identität ist akzent-genau (Kollation der DB ist akzent-unabhängig:
# 'max@x.at' = 'mäx@x.at'). Varianten dürfen nie Zugriff auf fremde Konten/Nennungen geben.
# Voraussetzung: MariaDB + PHP-Server auf localhost:8080. Aufruf: bash tests/email_identity_e2e.sh
set -u
B=http://localhost:8080
MYSQL="/c/Program Files/MariaDB 12.3/bin/mysql.exe"
cd "$(dirname "$0")/.."
FAILS=0
ok()  { echo "  ok   $1"; }
bad() { echo "  FAIL $1"; FAILS=$((FAILS+1)); }
q()   { "$MYSQL" -u root turnierverwaltung -N -e "$1"; }
tok() { php -r 'require "config.php"; require "lib/tokens.php"; echo $argv[1] === "reset" ? make_reset_token($argv[2], $argv[3]) : make_manage_email_token($argv[2]);' -- "$@"; }

VICTIM='opfer.e2e@beispiel.at'; VARIANT='opfer.e2e@beispiel.ät'
q "DELETE FROM user WHERE username='e2e-opfer'"
UID_=$(q "INSERT INTO user (username,email,password_hash,role,confirmed) VALUES ('e2e-opfer','$VICTIM','altes-hash','admin',1); SELECT LAST_INSERT_ID();")

# 1) Passwort-Reset mit gültigem Token für die Akzent-Variante darf das Opfer-Konto nicht erreichen
T=$(tok reset "$VARIANT" "altes-hash")
PAGE=$(curl -s -L "$B/reset-password?token=$T")
echo "$PAGE" | grep -q 'name="password2"' && bad "Reset-Variante: kein Formular für fremdes Konto" || ok "Reset-Variante: kein Formular für fremdes Konto"
# Kontrolle: mit der echten Adresse erscheint das Formular
T=$(tok reset "$VICTIM" "altes-hash")
curl -s -L "$B/reset-password?token=$T" | grep -q 'name="password2"' && ok "Reset echte Adresse: Formular" || bad "Reset echte Adresse: Formular"
# Groß/Klein ist dieselbe Adresse
T=$(tok reset "OPFER.E2E@beispiel.at" "altes-hash")
curl -s -L "$B/reset-password?token=$T" | grep -q 'name="password2"' && ok "Reset Großschreibung: Formular" || bad "Reset Großschreibung: Formular"

# 2) Nennungsverwaltung: Magic-Link der Variante zeigt keine Nennungen des Opfers
TID=$(q "INSERT INTO tournament (name,is_public,is_done) VALUES ('E2E Ident',1,0); SELECT LAST_INSERT_ID();")
q "INSERT INTO registration (tournament_id,lastname,firstname,email,status) VALUES ($TID,'Opferlich','Otto','$VICTIM','pending')"
MV=$(curl -s "$B/nennung/verwalten/$(tok manage "$VARIANT")")
echo "$MV" | grep -q 'Opferlich' && bad "Magic-Link-Variante: keine fremden Nennungen" || ok "Magic-Link-Variante: keine fremden Nennungen"
curl -s "$B/nennung/verwalten/$(tok manage "$VICTIM")" | grep -q 'Opferlich' && ok "Magic-Link echte Adresse: Nennung sichtbar" || bad "Magic-Link echte Adresse: Nennung sichtbar"

q "DELETE FROM tournament WHERE id=$TID; DELETE FROM user WHERE id=$UID_"
[ $FAILS -eq 0 ] && echo "Alle E2E-Tests ok" || { echo "$FAILS FEHLER"; exit 1; }
