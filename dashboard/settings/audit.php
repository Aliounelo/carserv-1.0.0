<?php
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

$pdo = db();
$rows = $pdo->query('SELECT id, user, role, action, entity, entity_id, details, ip, created_at FROM audit_logs ORDER BY id DESC LIMIT 200')->fetchAll();

render_header('Historique');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Historique des modifications</h5>
    <span class="admin-note">Visible uniquement par l’admin</span>
  </div>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="max-w-full overflow-x-auto">
      <table class="table admin-table min-w-[900px]">
        <thead class="border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Date</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Utilisateur</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Action</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Cible</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Détails</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Aucun historique.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="px-5 py-3 text-slate-500"><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td class="px-5 py-3 text-slate-700"><?php echo htmlspecialchars($r['user']); ?> <span class="text-xs text-slate-400">(<?php echo htmlspecialchars($r['role']); ?>)</span></td>
                <td class="px-5 py-3 text-slate-700"><?php echo htmlspecialchars($r['action']); ?></td>
                <td class="px-5 py-3 text-slate-600"><?php echo htmlspecialchars($r['entity']); ?> #<?php echo htmlspecialchars((string)$r['entity_id']); ?></td>
                <td class="px-5 py-3 text-slate-500 text-xs" style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?php echo htmlspecialchars((string)$r['details']); ?>
                </td>
                <td class="px-5 py-3 text-slate-400"><?php echo htmlspecialchars((string)$r['ip']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php render_footer(); ?>
