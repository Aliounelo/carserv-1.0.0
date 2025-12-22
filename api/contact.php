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

    $logPath = getenv('MAIL_DEV_LOG');

    // If MAIL_DEV_LOG is set (even to a path), log instead of sending
    if ($logPath !== false && $logPath !== '') {
        if ($logPath === '1' || $logPath === 'true') {
            $logPath = __DIR__ . '/mail_dev.log';
        }
        file_put_contents($logPath, "=== CONTACT ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
        return true;
    }

    $sent = mail($to, $subject, $body, implode("\r\n", $headers));

    if (!$sent) {
        // fallback log to help debugging in production if sending fails
        file_put_contents(__DIR__ . '/mail_dev.log', "=== CONTACT (FAILED SEND, LOGGED) ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
    }

    return $sent;
}

// Accept JSON or form-encoded
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$required = ['name', 'email', 'subject', 'message'];
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
$subject = trim($data['subject']);
$message = trim($data['message']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Email invalide');
}

$to = 'contact@marge-expert.com';
$from = 'contact@marge-expert.com';
$fullSubject = 'Contact MARGE - ' . $subject;
$body = "Nom: {$name}\nEmail: {$email}\nSujet: {$subject}\nMessage:\n{$message}";

$ok = send_mail_with_fallback($to, $from, $name, $email, $fullSubject, $body);

if ($ok) {
    respond(true);
} else {
    respond(false, 'Envoi impossible (mail)');
}
