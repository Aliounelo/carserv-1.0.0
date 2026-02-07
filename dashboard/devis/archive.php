<?php
declare(strict_types=1);
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../api/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}
$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
  http_response_code(403);
  exit('CSRF invalide');
}
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: /dashboard/devis/list.php');
  exit;
}
$pdo = db();
$now = date('Y-m-d H:i:s');
$pdo->prepare('UPDATE devis SET archived_at = :a WHERE id = :id')->execute([':a' => $now, ':id' => $id]);
audit_log('archive_devis', 'devis', $id, [], current_user(), current_role());
header('Location: /dashboard/devis/view.php?id=' . $id);
