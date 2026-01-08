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
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/html; charset=UTF-8";
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

$body = '
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Nouveau message – MARGE</title>
</head>
<body style="margin:0; padding:0; background-color:#f5fafb; font-family:Arial, Helvetica, sans-serif; color:#111827;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5fafb; padding:24px;">
    <tr>
      <td align="center">

        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">

          <!-- Header -->
          <tr>
            <td style="background-color:#007b8f; padding:18px 24px;">
              <h1 style="margin:0; font-size:18px; font-weight:600; color:#ffffff;">
                Nouveau message – Formulaire de contact
              </h1>
              <p style="margin:4px 0 0; font-size:13px; color:#e6f6f8;">
                MARGE · Mobilité & Logistique intégrées
              </p>
            </td>
          </tr>

          <!-- Content -->
          <tr>
            <td style="padding:24px;">

              <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">

                <tr>
                  <td style="padding:6px 0; width:140px; color:#6b7280;"><strong>Nom</strong></td>
                  <td style="padding:6px 0;">'.htmlspecialchars($name).'</td>
                </tr>

                <tr>
                  <td style="padding:6px 0; color:#6b7280;"><strong>Email</strong></td>
                  <td style="padding:6px 0;">
                    <a href="mailto:'.htmlspecialchars($email).'" style="color:#007b8f; text-decoration:none;">
                      '.htmlspecialchars($email).'
                    </a>
                  </td>
                </tr>

                <tr>
                  <td style="padding:6px 0; color:#6b7280;"><strong>Objet</strong></td>
                  <td style="padding:6px 0;">'.htmlspecialchars($subject).'</td>
                </tr>

              </table>

              <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0;">

              <p style="margin:0 0 8px; font-size:14px; font-weight:600; color:#007b8f;">
                Message
              </p>

              <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:14px; font-size:14px; line-height:1.5; white-space:pre-line;">
                '.nl2br(htmlspecialchars($message)).'
              </div>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f9fafb; padding:14px 24px; font-size:12px; color:#6b7280;">
              Reçu le '.date('d/m/Y à H:i').' via le site
              <a href="https://marge-expert.com" style="color:#007b8f; text-decoration:none;">marge-expert.com</a>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>';

$ok = send_mail_simple($config['to_contact'], $config['from_email'], $config['from_name'], $name, $email, "[MARGE][Contact] $subject", $body);

if ($ok) {
  echo json_encode(['ok'=>true,'message'=>'Message envoyé.']);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Erreur serveur."]);
}
