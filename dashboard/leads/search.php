<?php
declare(strict_types=1);
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';

$pdo = db();

$type = trim((string)($_GET['type'] ?? ''));
$service = trim((string)($_GET['service'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$priority = trim((string)($_GET['priority'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$archived = trim((string)($_GET['archived'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($type !== '') { $where[] = 'type = :type'; $params[':type'] = $type; }
if ($service !== '') { $where[] = 'service = :service'; $params[':service'] = $service; }
if ($status !== '') { $where[] = 'status = :status'; $params[':status'] = $status; }
if ($priority !== '') { $where[] = 'priority = :priority'; $params[':priority'] = $priority; }
if ($search !== '') { $where[] = '(name LIKE :q OR email LIKE :q OR service LIKE :q OR phone LIKE :q)'; $params[':q'] = '%' . $search . '%'; }
if ($from !== '') { $where[] = 'date(created_at) >= :from'; $params[':from'] = $from; }
if ($to !== '') { $where[] = 'date(created_at) <= :to'; $params[':to'] = $to; }
if ($archived === 'archived') { $where[] = 'archived_at IS NOT NULL'; }
elseif ($archived === 'all') { /* no filter */ }
else { $where[] = 'archived_at IS NULL'; }

$countSql = 'SELECT COUNT(*) AS c FROM leads';
if ($where) { $countSql .= ' WHERE ' . implode(' AND ', $where); }
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)($stmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$sql = 'SELECT id, type, name, email, phone, service, status, created_at FROM leads';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

function status_badge_class(string $status): string {
  return match ($status) {
    'Nouveau' => 'badge-new',
    'En cours de traitement' => 'badge-progress',
    "Contact\u{00E9}" => 'badge-contacte',
    "Devis envoy\u{00E9}" => 'badge-devis',
    'Converti' => 'badge-converti',
    "Abandonn\u{00E9}" => 'badge-abandonne',
    default => 'badge-new'
  };
}
function initials(string $name): string {
  $parts = preg_split('/\s+/', trim($name));
  $letters = '';
  foreach ($parts as $p) {
    if ($p !== '') {
      $letters .= strtoupper(substr($p, 0, 1));
    }
  }
  return substr($letters, 0, 2);
}

$tbody = '';
if (!$leads) {
  $tbody = '<tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Aucun lead pour ces filtres.</td></tr>';
} else {
  foreach ($leads as $lead) {
    $target = can_edit_leads() ? 'edit' : 'view';
    $tbody .= '<tr>';
    $tbody .= '<td class="px-5 py-4">';
    $tbody .= '<a href="/dashboard/leads/' . $target . '.php?id=' . (int)$lead['id'] . '" class="flex items-center gap-3 text-decoration-none">';
    $tbody .= '<div class="ta-avatar">' . htmlspecialchars(initials($lead['name'])) . '</div>';
    $tbody .= '<div><div class="font-medium text-slate-800">' . htmlspecialchars($lead['name']) . '</div>';
    $tbody .= '<div class="text-xs text-slate-500">' . htmlspecialchars($lead['email']) . ' &middot; ' . htmlspecialchars($lead['phone'] ?? '') . '</div></div>';
    $tbody .= '</a></td>';
    $tbody .= '<td class="px-5 py-4 text-slate-600">' . htmlspecialchars($lead['service'] ?? $lead['type']) . '</td>';
    $tbody .= '<td class="px-5 py-4"><a href="/dashboard/leads/' . $target . '.php?id=' . (int)$lead['id'] . '" class="text-decoration-none">';
    $tbody .= '<span class="badge-status ' . status_badge_class((string)$lead['status']) . '">' . htmlspecialchars($lead['status']) . '</span></a></td>';
    $tbody .= '<td class="px-5 py-4 text-slate-500">' . htmlspecialchars($lead['created_at']) . '</td>';
    $tbody .= '</tr>';
  }
}

$pagination = '';
$pagination .= '<div class="text-sm text-slate-500">Page ' . $page . ' / ' . $totalPages . ' &middot; ' . $total . ' r&eacute;sultats</div>';
$pagination .= '<div class="flex gap-2">';
if ($page > 1) {
  $pagination .= '<a class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700" href="?page=' . ($page - 1) . '">Pr&eacute;c&eacute;dent</a>';
}
if ($page < $totalPages) {
  $pagination .= '<a class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700" href="?page=' . ($page + 1) . '">Suivant</a>';
}
$pagination .= '</div>';

$countText = 'Page ' . $page . ' / ' . $totalPages . ' &middot; ' . $total . ' r&eacute;sultats';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'tbody' => $tbody,
  'countText' => $countText,
  'pagination' => $pagination
]);
