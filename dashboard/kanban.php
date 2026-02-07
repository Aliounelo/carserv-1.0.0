<?php

declare(strict_types=1);

require_once __DIR__ . '/_inc/auth.php';
require_login();
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_inc/layout.php';

$pdo = db();
$group = $_GET['group'] ?? 'service';

if ($group === 'month') {
  $rows = $pdo->query("SELECT strftime('%Y-%m', created_at) AS g, COUNT(*) AS c, MAX(created_at) AS last_at FROM leads WHERE archived_at IS NULL GROUP BY g ORDER BY g DESC")->fetchAll();
  $title = 'Pipeline par mois';
} else {
  $rows = $pdo->query("SELECT COALESCE(service,'Autre') AS g, COUNT(*) AS c, MAX(created_at) AS last_at,\n    SUM(CASE WHEN priority = 'Urgent' THEN 1 ELSE 0 END) AS urgent_count,\n    SUM(CASE WHEN priority = 'Haute' THEN 1 ELSE 0 END) AS high_count,\n    SUM(CASE WHEN priority = 'Normal' THEN 1 ELSE 0 END) AS normal_count,\n    SUM(CASE WHEN priority = 'Basse' THEN 1 ELSE 0 END) AS low_count\n    FROM leads WHERE archived_at IS NULL GROUP BY g\n    ORDER BY urgent_count DESC, high_count DESC, normal_count DESC, low_count DESC, c DESC")->fetchAll();
  $title = 'Pipeline par service';
}

render_header('Pipeline');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5><?php echo htmlspecialchars($title); ?></h5>
    <div class="d-flex gap-2">
      <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/kanban.php?group=service">Par service</a>
      <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/kanban.php?group=month">Par mois</a>
    </div>
  </div>
  <div class="row g-3">
    <?php foreach ($rows as $r): ?>
      <?php
        if ($group === 'month') {
          $link = '/dashboard/leads/list.php?from=' . htmlspecialchars($r['g']) . '-01&to=' . htmlspecialchars($r['g']) . '-31';
        } else {
          $link = '/dashboard/leads/list.php?service=' . urlencode($r['g']);
        }
      ?>
      <div class="col-12 col-md-6 col-lg-3">
        <a href="<?php echo $link; ?>" class="d-block p-3 rounded-xl border border-slate-200 bg-white text-decoration-none hover:shadow">
          <div class="text-sm text-slate-500"><?php echo htmlspecialchars($r['g']); ?></div>
          <div class="text-2xl font-semibold text-slate-900"><?php echo (int)$r['c']; ?></div>
          <div class="text-xs text-slate-400">Leads</div>
          <?php if ($group !== 'month'): ?>
            <div class="text-xs text-slate-500 mt-2">Urgent: <?php echo (int)$r['urgent_count']; ?> | Haute: <?php echo (int)$r['high_count']; ?> | Normal: <?php echo (int)$r['normal_count']; ?> | Basse: <?php echo (int)$r['low_count']; ?></div>
          <?php endif; ?>
          <div class="text-xs text-slate-500 mt-2">Dernier lead: <?php echo htmlspecialchars($r['last_at']); ?></div>
          <div class="text-xs text-teal-600 mt-2">Voir les leads</div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer(); ?>

