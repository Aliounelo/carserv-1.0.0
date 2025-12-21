<?php
header('Content-Type: application/json; charset=UTF-8');

function respond(bool $ok, ?string $msg = null): void {
    echo json_encode(['ok' => $ok, 'error' => $ok ? null : $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function send_mail_with_fallback(string $to, string $from, string $replyToName, string $replyToEmail, string $subject, string $body): bool {
    $headers = [];
    $headers[] = 'From: MARGE <' . $from . '>';
    $headers[] = 'Reply-To: ' . $replyToName . ' <' . $replyToEmail . '>';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    // Local/dev mode: if MAIL_DEV_LOG is set, log instead of sending
    $logFile = getenv('MAIL_DEV_LOG') ?: __DIR__ . '/mail_dev.log';
    if (getenv('MAIL_DEV_LOG')) {
        file_put_contents($logFile, "=== BOOKING ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
        return true;
    }

    $sent = mail($to, $subject, $body, implode("\r\n", $headers));

    if (!$sent && $logFile) {
        file_put_contents($logFile, "=== BOOKING (FAILED SEND, LOGGED) ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
    }

    return $sent;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$required = ['name', 'email', 'service', 'date', 'details'];
$missing = [];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        $missing[] = $field;
    }
}
if ($missing) {
    respond(false, 'Champs manquants: ' . implode(', ', $missing));
}

$name = trim($data['name']);
$email = trim($data['email']);
$service = trim($data['service']);
$date = trim($data['date']);
$details = trim($data['details']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Email invalide');
}

$to = 'contact@marge-expert.com';
$from = 'contact@marge-expert.com';
$subject = 'Réservation MARGE - ' . $service;
$body = "Nom: {$name}\nEmail: {$email}\nService: {$service}\nDate souhaitée: {$date}\nDétails:\n{$details}";

$ok = send_mail_with_fallback($to, $from, $name, $email, $subject, $body);

if ($ok) {
    respond(true);
} else {
    respond(false, 'Envoi impossible (mail)');
}
