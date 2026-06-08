<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail(string $to, string $subject, string $html_body): bool {
    if (!MAIL_HOST) {
        // Dev-Modus: Link in Flash anzeigen
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_TLS ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM ?: MAIL_USERNAME, 'Turnierverwaltung');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->send();
        return true;
    } catch (Exception) {
        return false;
    }
}

function send_confirm_mail(string $to, string $token): void {
    $link = url('confirm?token=' . urlencode($token));
    $sent = send_mail(
        $to,
        'E-Mail-Adresse bestätigen',
        '<p>Bitte bestätige deine E-Mail-Adresse:</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
    );
    if (!$sent) {
        flash('info', 'Dev-Bestätigungslink: <a href="' . e($link) . '">' . e($link) . '</a>', true);
    }
}

function send_reset_mail(string $to, string $token): void {
    $link = url('reset-password?token=' . urlencode($token));
    $sent = send_mail(
        $to,
        'Passwort zurücksetzen',
        '<p>Klicke hier, um dein Passwort zurückzusetzen (gültig 1 Stunde):</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
    );
    if (!$sent) {
        flash('info', 'Dev-Reset-Link: <a href="' . e($link) . '">' . e($link) . '</a>', true);
    }
}

function send_reg_manage_mail(string $to, string $token): void {
    $link = url('nennung/verwalten/' . urlencode($token));
    $sent = send_mail(
        $to,
        'Turnierverwaltung – Nennungen verwalten',
        '<p>Hallo,</p>'
        . '<p>Mit dem folgenden Link kannst du deine Nennungen einsehen und verwalten:</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p>Der Link ist 7 Tage gültig.</p>'
    );
    if (!$sent) {
        flash('info', 'Dev-Verwaltungslink: <a href="' . e($link) . '">' . e($link) . '</a>', true);
    }
}

function send_change_request_processed_mail(string $to, string $name, string $tournament,
                                              string $request_type, array $comp_results, ?string $token): void {
    $body  = '<p>Hallo ' . htmlspecialchars($name) . ',</p>';
    if ($request_type === 'withdraw') {
        $body .= '<p>dein Rückzugsantrag für <strong>' . htmlspecialchars($tournament)
               . '</strong> wurde bestätigt. Deine Nennung wurde zurückgezogen.</p>';
    } else {
        $body .= '<p>dein Änderungsantrag für <strong>' . htmlspecialchars($tournament) . '</strong> wurde bearbeitet.</p>';
        $added        = array_column(array_filter($comp_results, fn($c) => $c['action'] === 'add'    && $c['status'] === 'confirmed'), 'name');
        $removed      = array_column(array_filter($comp_results, fn($c) => $c['action'] === 'remove' && $c['status'] === 'confirmed'), 'name');
        $rejected_add = array_column(array_filter($comp_results, fn($c) => $c['action'] === 'add'    && $c['status'] === 'rejected'),  'name');
        $rejected_rem = array_column(array_filter($comp_results, fn($c) => $c['action'] === 'remove' && $c['status'] === 'rejected'),  'name');
        if ($added) {
            $body .= '<p><strong>Angemeldet:</strong></p><ul>';
            foreach ($added as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
            $body .= '</ul>';
        }
        if ($removed) {
            $body .= '<p><strong>Abgemeldet:</strong></p><ul>';
            foreach ($removed as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
            $body .= '</ul>';
        }
        if ($rejected_add) {
            $body .= '<p><strong>Anmeldung abgelehnt:</strong></p><ul>';
            foreach ($rejected_add as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
            $body .= '</ul>';
        }
        if ($rejected_rem) {
            $body .= '<p><strong>Abmeldung abgelehnt:</strong></p><ul>';
            foreach ($rejected_rem as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
            $body .= '</ul>';
        }
    }
    if ($token) {
        $link  = url('nennung/verwalten/' . urlencode($token));
        $body .= '<p>Mit folgendem Link kannst du deine Nennungen verwalten (gültig 7 Tage):</p>'
               . '<p><a href="' . $link . '">' . $link . '</a></p>';
    }
    $sent = send_mail($to, 'Turnierverwaltung – Nennungsänderung bearbeitet', $body);
    if (!$sent) {
        flash('info', 'Dev-Mail an ' . e($to) . ': Änderungsantrag für ' . htmlspecialchars($tournament) . ' bearbeitet.', true);
    }
}

function send_reg_processed_mail(string $to, string $name, string $tournament,
                                  array $confirmed, array $rejected, ?string $token): void {
    $body  = '<p>Hallo ' . htmlspecialchars($name) . ',</p>';
    $body .= '<p>deine Nennung für <strong>' . htmlspecialchars($tournament) . '</strong> wurde bearbeitet.</p>';
    if (!empty($confirmed)) {
        $body .= '<p><strong>Bestätigt:</strong></p><ul>';
        foreach ($confirmed as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
        $body .= '</ul>';
    }
    if (!empty($rejected)) {
        $body .= '<p><strong>Abgelehnt:</strong></p><ul>';
        foreach ($rejected as $c) $body .= '<li>' . htmlspecialchars($c) . '</li>';
        $body .= '</ul>';
    }
    if ($token) {
        $link  = url('nennung/verwalten/' . urlencode($token));
        $body .= '<p>Mit folgendem Link kannst du deine Nennungen verwalten (gültig 7 Tage):</p>'
               . '<p><a href="' . $link . '">' . $link . '</a></p>';
    }
    $sent = send_mail($to, 'Turnierverwaltung – Nennung bearbeitet', $body);
    if (!$sent) {
        $parts = [];
        if ($confirmed) $parts[] = 'Bestätigt: ' . implode(', ', array_map('htmlspecialchars', $confirmed));
        if ($rejected)  $parts[] = 'Abgelehnt: '  . implode(', ', array_map('htmlspecialchars', $rejected));
        $info = 'Dev-Mail an ' . e($to) . ($parts ? ': ' . implode(' | ', $parts) : '');
        if ($token) {
            $link  = url('nennung/verwalten/' . urlencode($token));
            $info .= ' | <a href="' . e($link) . '">Verwaltungslink</a>';
        }
        flash('info', $info, true);
    }
}
