<?php

declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

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

render_header('Leads');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Filtres</h5>
  </div>
  <form class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4" method="get" data-auto-filter="1" data-ajax-target="leads">
    <div>
      <label class="text-sm font-medium text-slate-600">Type</label>
      <select name="type" class="ta-input w-full">
        <option value="">Tous</option>
        <option value="contact" <?php echo $type==='contact'?'selected':''; ?>>Contact</option>
        <option value="booking" <?php echo $type==='booking'?'selected':''; ?>>Booking</option>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Statut</label>
      <select name="status" class="ta-input w-full">
        <option value="">Tous</option>
        <?php foreach (['Nouveau','En cours de traitement',"Contact\u{00E9}","Devis envoy\u{00E9}",'Converti',"Abandonn\u{00E9}"] as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Priorit&eacute;</label>
      <select name="priority" class="ta-input w-full">
        <option value="">Toutes</option>
        <?php foreach (['Basse','Normal','Haute','Urgent'] as $p): ?>
          <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $priority===$p?'selected':''; ?>><?php echo htmlspecialchars($p); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Service</label>
      <input type="text" name="service" class="ta-input w-full" value="<?php echo htmlspecialchars($service); ?>" placeholder="Transport, location...">
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Archiv&eacute;s</label>
      <select name="archived" class="ta-input w-full">
        <option value="" <?php echo $archived===''?'selected':''; ?>>Actifs</option>
        <option value="archived" <?php echo $archived==='archived'?'selected':''; ?>>Archiv&eacute;s</option>
        <option value="all" <?php echo $archived==='all'?'selected':''; ?>>Tous</option>
      </select>
    </div>
    <div class="md:col-span-2">
      <label class="text-sm font-medium text-slate-600">Recherche</label>
      <input type="text" name="q" class="ta-input w-full" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, email, service" autocomplete="off">
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Du</label>
      <input type="date" name="from" class="ta-input w-full" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Au</label>
      <input type="date" name="to" class="ta-input w-full" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="flex items-end">
      <button class="w-full rounded-xl bg-teal-600 text-white py-2.5 font-semibold">Filtrer</button>
    </div>
    <div class="flex items-end">
      <a class="w-full rounded-xl border border-slate-200 text-slate-700 py-2.5 text-center" href="/dashboard/leads/export.php?<?php echo http_build_query($_GET); ?>">Exporter CSV</a>
    </div>
  </form>
</div>

<div class="admin-card">
  <div class="admin-section-title">
    <h5>Liste des leads</h5>
    <div class="text-sm text-slate-500" id="leads-count">Page <?php echo $page; ?> / <?php echo $totalPages; ?> &middot; <?php echo $total; ?> r&eacute;sultats</div>
  </div>
  <div id="leads-loader" class="hidden text-sm text-slate-500 mb-2">Chargement...</div>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="max-w-full overflow-x-auto">
      <table class="table admin-table min-w-[900px]">
        <thead class="border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Lead</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Service</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Statut</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="leads-tbody">
          <?php if (!$leads): ?>
            <tr>
              <td colspan="4" class="px-5 py-8 text-center text-slate-400">Aucun lead pour ces filtres.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($leads as $lead): ?>
              <tr>
                <td class="px-5 py-4">
                  <a href="/dashboard/leads/<?php echo can_edit_leads() ? 'edit' : 'view'; ?>.php?id=<?php echo (int)$lead['id']; ?>" class="flex items-center gap-3 text-decoration-none">
                    <div class="ta-avatar"><?php echo htmlspecialchars(initials($lead['name'])); ?></div>
                    <div>
                      <div class="font-medium text-slate-800"><?php echo htmlspecialchars($lead['name']); ?></div>
                      <div class="text-xs text-slate-500"><?php echo htmlspecialchars($lead['email']); ?> &middot; <?php echo htmlspecialchars($lead['phone'] ?? ''); ?></div>
                    </div>
                  </a>
                </td>
                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($lead['service'] ?? $lead['type']); ?></td>
                <td class="px-5 py-4">
                  <a href="/dashboard/leads/<?php echo can_edit_leads() ? 'edit' : 'view'; ?>.php?id=<?php echo (int)$lead['id']; ?>" class="text-decoration-none">
                    <span class="badge-status <?php echo status_badge_class((string)$lead['status']); ?>"><?php echo htmlspecialchars($lead['status']); ?></span>
                  </a>
                </td>
                <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($lead['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2" id="leads-pagination">
    <div class="text-sm text-slate-500">Page <?php echo $page; ?> / <?php echo $totalPages; ?> &middot; <?php echo $total; ?> r&eacute;sultats</div>
    <div class="flex gap-2">
      <?php if ($page > 1): ?>
        <a class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Pr&eacute;c&eacute;dent</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Suivant</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
  (function () {
    const form = document.querySelector('form[data-ajax-target="leads"]');
    const tbody = document.getElementById('leads-tbody');
    const countEl = document.getElementById('leads-count');
    const loader = document.getElementById('leads-loader');
    const pagination = document.getElementById('leads-pagination');
    if (!form || !tbody) return;

    let inFlight = null;
    const fetchResults = () => {
      if (loader) loader.classList.remove('hidden');
      const fd = new FormData(form);
      fd.set('page', '1');
      const params = new URLSearchParams(fd);
      if (inFlight) inFlight.abort();
      inFlight = new AbortController();
      fetch('/dashboard/leads/search.php?' + params.toString(), { signal: inFlight.signal })
        .then(r => r.json())
        .then(data => {
          if (data && data.tbody) tbody.innerHTML = data.tbody;
          if (countEl && data.countText) countEl.innerHTML = data.countText;
          if (pagination && data.pagination) pagination.innerHTML = data.pagination;
        })
        .catch(() => {})
        .finally(() => {
          if (loader) loader.classList.add('hidden');
        });
    };

    form.addEventListener('input', (e) => {
      const t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'SELECT')) {
        clearTimeout(window.__leadTimer);
        window.__leadTimer = setTimeout(fetchResults, 300);
      }
    });
    form.addEventListener('change', () => {
      clearTimeout(window.__leadTimer);
      window.__leadTimer = setTimeout(fetchResults, 200);
    });
  })();
</script>
<?php render_footer(); ?>
