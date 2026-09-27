<?php
// CLI-Test Tokens: php tests/tokens_test.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nie per Webserver ausführen
chdir(__DIR__ . '/..');
require 'config.php'; require 'lib/tokens.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
    if (!$ok) $fails++;
}

echo "Reset-Token\n";
$hash = '$argon2id$v=19$m=65536,t=4,p=1$GEHEIMERHASHTEIL';
$tok  = make_reset_token('a@beispiel.at', $hash);
$payload = base64_decode(strtr(explode('.', $tok)[0], '-_', '+/'));
check('Passwort-Hash steht nicht im Token', !str_contains($payload, 'GEHEIMERHASHTEIL') && !str_contains($tok, 'GEHEIMERHASHTEIL'));
[$email, $fp] = verify_reset_token($tok);
check('E-Mail wird zurückgegeben', $email === 'a@beispiel.at');
check('passt zum aktuellen Hash', reset_token_matches($fp, $hash));
check('passt nicht nach Passwortänderung', !reset_token_matches($fp, $hash . 'x'));
check('manipulierter Token ungültig', verify_reset_token($tok . 'x') === [null, null]);

echo $fails ? "\n$fails FEHLER\n" : "\nAlle Tests ok\n";
exit($fails ? 1 : 0);
