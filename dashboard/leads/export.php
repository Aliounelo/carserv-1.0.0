<?php

declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';

$pdo = db();

$type = trim((string)($_GET['type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$priority = trim((string)($_GET['priority'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$where = [];
$params = [];
if ($type !== '') { $where[] = 'type = :type'; $params[':type'] = $type; }
if ($status !== '') { $where[] = 'status = :status'; $params[':status'] = $status; }
if ($priority !== '') { $where[] = 'priority = :priority'; $params[':priority'] = $priority; }
if ($search !== '') { $where[] = '(name LIKE :q OR email LIKE :q OR service LIKE :q OR phone LIKE :q)'; $params[':q'] = '%' . $search . '%'; }
if ($from !== '') { $where[] = 'date(created_at) >= :from'; $params[':from'] = $from; }
if ($to !== '') { $where[] = 'date(created_at) <= :to'; $params[':to'] = $to; }

$sql = 'SELECT id, type, name, email, phone, service, status, priority, created_at FROM leads';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','type','name','email','phone','service','status','priority','created_at']);
foreach ($rows as $r) {
  fputcsv($out, [$r['id'],$r['type'],$r['name'],$r['email'],$r['phone'],$r['service'],$r['status'],$r['priority'],$r['created_at']]);
}
fclose($out);
