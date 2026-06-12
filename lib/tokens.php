<?php
// HMAC-basierte Tokens — Ersatz für Python's itsdangerous.URLSafeTimedSerializer
// Format: base64url(payload_json) . "." . base64url(timestamp) . "." . base64url(signature)

function _b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _b64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function make_token(mixed $payload, string $salt): string {
    $p   = _b64url_encode(json_encode($payload));
    $ts  = _b64url_encode((string)time());
    $sig = _b64url_encode(hash_hmac('sha256', "$p.$ts.$salt", SECRET_KEY, true));
    return "$p.$ts.$sig";
}

function verify_token(string $token, string $salt, int $max_age = 86400): mixed {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$p, $ts, $sig] = $parts;

    $expected_sig = _b64url_encode(hash_hmac('sha256', "$p.$ts.$salt", SECRET_KEY, true));
    if (!hash_equals($expected_sig, $sig)) return null;

    $timestamp = (int)(_b64url_decode($ts) ?: 0);
    if ((time() - $timestamp) > $max_age) return null;

    $payload = _b64url_decode($p);
    return $payload !== false ? json_decode($payload, true) : null;
}

// Convenience-Wrapper
function make_email_confirm_token(string $email): string {
    return make_token($email, 'email-confirm');
}

function verify_email_confirm_token(string $token): ?string {
    $payload = verify_token($token, 'email-confirm', 86400); // 24h
    return is_string($payload) ? $payload : null;
}

function make_reset_token(string $email, string $password_hash): string {
    return make_token(['e' => $email, 'ph' => $password_hash], 'password-reset');
}

function verify_reset_token(string $token): array {
    $data = verify_token($token, 'password-reset', 3600); // 1h
    if (!is_array($data) || empty($data['e'])) return [null, null];
    return [$data['e'], $data['ph']];
}

// Email-basierter Nennungs-Verwaltungslink (zeigt alle Nennungen dieser Email)
function make_manage_email_token(string $email): string {
    return make_token(strtolower($email), 'reg-manage-email');
}

function verify_manage_email_token(string $token): ?string {
    $payload = verify_token($token, 'reg-manage-email', 7 * 24 * 3600); // 7 Tage
    return is_string($payload) ? $payload : null;
}
