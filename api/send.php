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

// Déduplication courte pour éviter les doublons (double-clic / refresh)
function already_sent_recent(string $hash, int $ttl = 90): bool {
  $dir = sys_get_temp_dir() . '/marge_dup/';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $file = $dir . md5($hash) . '.txt';
  $now  = time();
  if (file_exists($file)) {
    $last = (int)file_get_contents($file);
    if (($now - $last) < $ttl) {
      return true;
    }
  }
  file_put_contents($file, (string)$now);
  return false;
}

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

// Stop duplicate submissions within a short window
$fingerprint = implode('|', [$email, $subject, substr($message, 0, 120)]);
if (already_sent_recent($fingerprint, 90)) {
  echo json_encode(['ok'=>true,'message'=>'Déjà envoyé (duplication ignorée).']);
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
