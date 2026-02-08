<?php
declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

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

render_header('Devis');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Devis</h5>
    <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/devis/create.php">Cr&eacute;er un devis</a>
  </div>
  <div class="text-sm text-slate-500 mb-2" id="devis-count"><?php echo count($rows); ?> r&eacute;sultats</div>
  <form class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4" method="get" data-auto-filter="1" data-ajax-target="devis">
    <div>
      <label class="text-sm font-medium text-slate-600">Recherche</label>
      <input type="text" name="q" class="ta-input w-full" value="<?php echo htmlspecialchars($q); ?>" placeholder="R&eacute;f&eacute;rence, client, email" autocomplete="off">
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Statut</label>
      <select name="status" class="ta-input w-full">
        <option value="">Tous</option>
        <option value="Brouillon" <?php echo $status==='Brouillon'?'selected':''; ?>>Brouillon</option>
        <option value="Envoyé" <?php echo $status==='Envoyé'?'selected':''; ?>>Envoy&eacute;</option>
      </select>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Archiv&eacute;s</label>
      <select name="archived" class="ta-input w-full">
        <option value="" <?php echo $archived===''?'selected':''; ?>>Actifs</option>
        <option value="archived" <?php echo $archived==='archived'?'selected':''; ?>>Archiv&eacute;s</option>
        <option value="all" <?php echo $archived==='all'?'selected':''; ?>>Tous</option>
      </select>
    </div>
  </form>
  <div id="devis-loader" class="hidden text-sm text-slate-500 mb-2">Chargement...</div>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="max-w-full overflow-x-auto">
      <table class="table admin-table min-w-[900px]">
        <thead class="border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">R&eacute;f&eacute;rence</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Lead</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Montant</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Statut</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Date</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="devis-tbody">
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Aucun devis.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="px-5 py-4 font-medium text-slate-800"><?php echo htmlspecialchars($r['reference']); ?></td>
                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($r['lead_name'] ?? ''); ?><br><span class="text-xs text-slate-400"><?php echo htmlspecialchars($r['lead_email'] ?? ''); ?></span></td>
                <td class="px-5 py-4 text-slate-600"><?php echo number_format((float)$r['amount'], 0, ',', ' '); ?> <?php echo htmlspecialchars($r['currency']); ?></td>
                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($r['status']); ?></td>
                <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td class="px-5 py-4">
                  <a class="text-teal-700" href="/dashboard/devis/view.php?id=<?php echo (int)$r['id']; ?>">Ouvrir</a>
                  <?php if (($r['status'] ?? '') !== 'Envoyé'): ?>
                    <span class="text-slate-300 px-2">|</span>
                    <a class="text-slate-600" href="/dashboard/devis/edit.php?id=<?php echo (int)$r['id']; ?>">Modifier</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  (function () {
    const form = document.querySelector('form[data-ajax-target="devis"]');
    const tbody = document.getElementById('devis-tbody');
    const loader = document.getElementById('devis-loader');
    const countEl = document.getElementById('devis-count');
    if (!form || !tbody) return;
    let inFlight = null;
    const fetchResults = () => {
      if (loader) loader.classList.remove('hidden');
      const fd = new FormData(form);
      const params = new URLSearchParams(fd);
      if (inFlight) inFlight.abort();
      inFlight = new AbortController();
      fetch('/dashboard/devis/search.php?' + params.toString(), { signal: inFlight.signal })
        .then(r => r.json())
        .then(data => {
          if (data && data.tbody) tbody.innerHTML = data.tbody;
          if (countEl && data.countText) countEl.innerHTML = data.countText;
        })
        .catch(() => {})
        .finally(() => { if (loader) loader.classList.add('hidden'); });
    };

    form.addEventListener('input', () => {
      clearTimeout(window.__devisTimer);
      window.__devisTimer = setTimeout(fetchResults, 300);
    });
    form.addEventListener('change', () => {
      clearTimeout(window.__devisTimer);
      window.__devisTimer = setTimeout(fetchResults, 200);
    });
  })();
</script>
<?php render_footer(); ?>
