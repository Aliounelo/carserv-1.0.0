<?php
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../api/db.php';

$pdo = db();
$rows = $pdo->query('SELECT id, user, role, action, entity, entity_id, details, ip, created_at FROM audit_logs ORDER BY id DESC')->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_logs.csv"');

$fp = fopen('php://output', 'w');
fputcsv($fp, ['ID', 'Date', 'Utilisateur', 'Role', 'Action', 'Cible', 'ID Cible', 'Details', 'IP']);
foreach ($rows as $r) {
  fputcsv($fp, [
    $r['id'],
    $r['created_at'],
    $r['user'],
    $r['role'],
    $r['action'],
    $r['entity'],
    $r['entity_id'],
    $r['details'],
    $r['ip']
  ]);
}

fclose($fp);
exit;

