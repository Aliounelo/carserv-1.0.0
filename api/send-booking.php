<?php

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/rate_limit.php';
rate_limit_or_die((int)$config['max_per_15min'], 900);

function t($v): string { return trim((string)($v ?? '')); }
function emailv($v): string { return trim(filter_var($v ?? '', FILTER_SANITIZE_EMAIL)); }

function send_mail_simple(string $to, string $from, string $fromName, string $replyName, string $replyEmail, string $subject, string $body): bool {
  $headers = [];
  $headers[] = "From: {$fromName} <{$from}>";
  $headers[] = "Reply-To: {$replyName} <{$replyEmail}>";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";
  $headersStr = implode("\r\n", $headers);

  $logPath = __DIR__ . '/mail_dev.log';
  $sent = mail($to, $subject, $body, $headersStr);
  if (!$sent) {
    file_put_contents($logPath, "=== BOOKING (FAILED SEND, LOGGED) ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
  }
  return $sent;
}

$name    = t($_POST['name'] ?? '');
$email   = emailv($_POST['email'] ?? '');
$service = t($_POST['service'] ?? '');
$date    = t($_POST['date'] ?? '');
$details = trim((string)($_POST['details'] ?? ''));

$hp = t($_POST['website'] ?? '');
if ($hp !== '') { echo json_encode(['ok'=>true]); exit; }

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $service === '' || $details === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Champs invalides.']);
  exit;
}

$body = "Nouvelle demande (Booking/Devis)\n\n"
      . "Nom: $name\nEmail: $email\nService: $service\nDate: $date\n\n"
      . "Détails:\n$details\n";

$ok = send_mail_simple($config['to_booking'], $config['from_email'], $config['from_name'], $name, $email, "[MARGE][Demande] $service", $body);

if ($ok) {
  echo json_encode(['ok'=>true,'message'=>'Demande envoyée.']);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Erreur serveur."]);
}
