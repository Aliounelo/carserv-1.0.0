<?php
declare(strict_types=1);
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';

$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$archived = trim((string)($_GET['archived'] ?? ''));

$where = [];
$params = [];
if ($q !== '') { $where[] = '(d.reference LIKE :q OR l.name LIKE :q OR l.email LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
if ($status !== '') { $where[] = 'd.status = :status'; $params[':status'] = $status; }
if ($archived === 'archived') { $where[] = 'd.archived_at IS NOT NULL'; }
elseif ($archived === 'all') { /* no filter */ }
else { $where[] = 'd.archived_at IS NULL'; }

$sql = 'SELECT d.id, d.reference, d.amount, d.currency, d.status, d.created_at, l.name AS lead_name, l.email AS lead_email FROM devis d LEFT JOIN leads l ON l.id = d.lead_id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY d.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$tbody = '';
if (!$rows) {
  $tbody = '<tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Aucun devis.</td></tr>';
} else {
  foreach ($rows as $r) {
    $tbody .= '<tr>';
    $tbody .= '<td class="px-5 py-4 font-medium text-slate-800">' . htmlspecialchars($r['reference']) . '</td>';
    $tbody .= '<td class="px-5 py-4 text-slate-600">' . htmlspecialchars($r['lead_name'] ?? '') . '<br><span class="text-xs text-slate-400">' . htmlspecialchars($r['lead_email'] ?? '') . '</span></td>';
    $tbody .= '<td class="px-5 py-4 text-slate-600">' . number_format((float)$r['amount'], 0, ',', ' ') . ' ' . htmlspecialchars($r['currency']) . '</td>';
    $tbody .= '<td class="px-5 py-4 text-slate-600">' . htmlspecialchars($r['status']) . '</td>';
    $tbody .= '<td class="px-5 py-4 text-slate-500">' . htmlspecialchars($r['created_at']) . '</td>';
    $tbody .= '<td class="px-5 py-4">';
    $tbody .= '<a class="text-teal-700" href="/dashboard/devis/view.php?id=' . (int)$r['id'] . '">Ouvrir</a>';
    if (($r['status'] ?? '') !== 'Envoyé') {
      $tbody .= '<span class="text-slate-300 px-2">|</span>';
      $tbody .= '<a class="text-slate-600" href="/dashboard/devis/edit.php?id=' . (int)$r['id'] . '">Modifier</a>';
    }
    $tbody .= '</td>';
    $tbody .= '</tr>';
  }
}

$countText = count($rows) . ' r&eacute;sultats';

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['tbody' => $tbody, 'countText' => $countText]);
