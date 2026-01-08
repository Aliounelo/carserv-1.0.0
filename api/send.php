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
    file_put_contents($logPath, "=== CONTACT (FAILED SEND, LOGGED) ===\nSubject: $subject\n$body\n\n", FILE_APPEND);
  }
  return $sent;
}

$name    = t($_POST['name'] ?? '');
$email   = emailv($_POST['email'] ?? '');
$subject = t($_POST['subject'] ?? '');
$message = trim((string)($_POST['message'] ?? ''));

// Honeypot anti-bot (champ caché optionnel)
$hp = t($_POST['website'] ?? '');
if ($hp !== '') { echo json_encode(['ok'=>true]); exit; }

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Champs invalides.']);
  exit;
}

$body = "Nouveau message (Contact)\n\n"
      . "Nom: $name\nEmail: $email\nObjet: $subject\n\n"
      . "Message:\n$message\n";

$ok = send_mail_simple($config['to_contact'], $config['from_email'], $config['from_name'], $name, $email, "[MARGE][Contact] $subject", $body);

if ($ok) {
  echo json_encode(['ok'=>true,'message'=>'Message envoyé.']);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Erreur serveur."]);
}
