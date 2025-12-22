<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/rate_limit.php';
rate_limit_or_die((int)$config['max_per_15min'], 900);

function t($v): string { return trim((string)($v ?? '')); }
function emailv($v): string { return trim(filter_var($v ?? '', FILTER_SANITIZE_EMAIL)); }

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
  $mail->addAddress($config['to_booking']);
  $mail->addReplyTo($email, $name);

  $mail->Subject = "[MARGE][Demande] " . $service;

  $body = "Nouvelle demande (Booking/Devis)\n\n"
        . "Nom: $name\nEmail: $email\nService: $service\nDate: $date\n\n"
        . "Détails:\n$details\n";

  $mail->Body = $body;
  $mail->send();

  echo json_encode(['ok'=>true,'message'=>'Demande envoyée.']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Erreur d'envoi email."]);
}
