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

try {
  require __DIR__ . '/../vendor/autoload.php';
  $mail = new PHPMailer\PHPMailer\PHPMailer(true);

  $mail->isSMTP();
  $mail->Host       = $config['smtp_host'];
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['smtp_user'];
  $mail->Password   = $config['smtp_pass'];
  $mail->SMTPSecure = $config['smtp_secure'];
  $mail->Port       = (int)$config['smtp_port'];

  $mail->CharSet = 'UTF-8';
  $mail->setFrom($config['from_email'], $config['from_name']);
  $mail->addAddress($config['to_contact']);
  $mail->addReplyTo($email, $name);

  $mail->Subject = "[MARGE][Contact] " . $subject;

  $body = "Nouveau message (Contact)\n\n"
        . "Nom: $name\nEmail: $email\nObjet: $subject\n\n"
        . "Message:\n$message\n";

  $mail->Body = $body;
  $mail->send();

  echo json_encode(['ok'=>true,'message'=>'Message envoyé.']);
} catch (Throwable $e) {
  error_log("MAIL ERROR: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Erreur serveur."]);
}
