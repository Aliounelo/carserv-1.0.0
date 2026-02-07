<?php
declare(strict_types=1);
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';

$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$items = [];
$count = 0;
if (strlen($q) >= 2) {
  $stmt = $pdo->prepare('SELECT id, name, email FROM leads WHERE archived_at IS NULL AND (name LIKE :q OR email LIKE :q OR phone LIKE :q) ORDER BY id DESC LIMIT 5');
  $stmt->execute([':q' => '%' . $q . '%']);
  $items = $stmt->fetchAll();

  $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE archived_at IS NULL AND (name LIKE :q OR email LIKE :q OR phone LIKE :q)');
  $cnt->execute([':q' => '%' . $q . '%']);
  $count = (int)($cnt->fetch()['c'] ?? 0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['items' => $items, 'count' => $count]);
