<?php

require_once __DIR__ . '/_inc/auth.php';
require_login();
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_inc/layout.php';

$pdo = db();

$todayStart = date('Y-m-d 00:00:00');
$tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));
$weekStart = date('Y-m-d 00:00:00', strtotime('-7 day'));
$monthStart = date('Y-m-d 00:00:00', strtotime('-30 day'));

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE archived_at IS NULL AND created_at >= :start AND created_at < :end');
$stmt->execute([':start' => $todayStart, ':end' => $tomorrow]);
$todayCount = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE archived_at IS NULL AND created_at >= :start');
$stmt->execute([':start' => $weekStart]);
$weekCount = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE archived_at IS NULL AND created_at >= :start');
$stmt->execute([':start' => $monthStart]);
$monthCount = (int)($stmt->fetch()['c'] ?? 0);

$counts = [
  'today' => $todayCount,
  'week' => $weekCount,
  'month' => $monthCount,
];

$latest = $pdo->query("SELECT id, type, name, email, service, status, created_at FROM leads WHERE archived_at IS NULL ORDER BY id DESC LIMIT 8")->fetchAll();

function status_badge_class(string $status): string {
  return match ($status) {
    'Nouveau' => 'badge-new',
    'En cours de traitement' => 'badge-progress',
    'Contacté' => 'badge-contacte',
    'Devis envoyé' => 'badge-devis',
    'Converti' => 'badge-converti',
    'Abandonné' => 'badge-abandonne',
    default => 'badge-new'
  };
}

$chartRows = $pdo->query("SELECT date(created_at) AS d, COUNT(*) AS c FROM leads WHERE archived_at IS NULL AND created_at >= datetime('now','-29 day') GROUP BY date(created_at) ORDER BY d")->fetchAll();
$chartMap = [];
foreach ($chartRows as $row) {
  $chartMap[$row['d']] = (int)$row['c'];
}
$chartLabels = [];
$chartValues = [];
for ($i = 29; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $chartLabels[] = $d;
  $chartValues[] = $chartMap[$d] ?? 0;
}

render_header('Vue globale');
?>
<div class="row g-4">
  <div class="col-12 col-md-4">
    <div class="admin-card admin-stat">
      <div>
        <h6>Leads aujourd'hui</h6>
        <div class="admin-stat-value"><?php echo (int)$counts['today']; ?></div>
      </div>
      <span class="admin-chip">Temps r&eacute;el</span>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="admin-card admin-stat">
      <div>
        <h6>Leads 7 jours</h6>
        <div class="admin-stat-value"><?php echo (int)$counts['week']; ?></div>
      </div>
      <span class="admin-chip">Hebdo</span>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="admin-card admin-stat">
      <div>
        <h6>Leads 30 jours</h6>
        <div class="admin-stat-value"><?php echo (int)$counts['month']; ?></div>
      </div>
      <span class="admin-chip">Mensuel</span>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Activit&eacute; mensuelle</h5>
        <span class="admin-note">30 derniers jours</span>
      </div>
      <div class="h-56">
        <canvas id="leadsChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Objectif du mois</h5>
      </div>
      <p class="admin-note">Suivi des leads prioritaires &agrave; traiter.</p>
      <div class="mt-4">
        <div class="d-flex justify-content-between text-sm mb-2"><span>Traitement</span><span>65%</span></div>
        <div class="w-100 h-3 rounded bg-slate-100">
          <div class="h-3 rounded bg-teal-500" style="width:65%"></div>
        </div>
      </div>
      <div class="mt-4">
        <div class="d-flex justify-content-between text-sm mb-2"><span>Devis envoy&eacute;s</span><span>40%</span></div>
        <div class="w-100 h-3 rounded bg-slate-100">
          <div class="h-3 rounded bg-amber-400" style="width:40%"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Priorit&eacute;s</h5>
      </div>
      <ul class="list-unstyled mb-0">
        <li class="d-flex justify-content-between py-2 border-bottom">Leads urgents <strong>3</strong></li>
        <li class="d-flex justify-content-between py-2 border-bottom">Leads en attente <strong>7</strong></li>
        <li class="d-flex justify-content-between py-2">Devis &agrave; envoyer <strong>2</strong></li>
      </ul>
    </div>
  </div>
  <div class="col-12">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Derniers leads</h5>
        <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/leads/list.php">Voir tout</a>
      </div>
      <div class="overflow-x-auto">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Nom</th>
              <th>Email</th>
              <th>Service</th>
              <th>Statut</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latest as $lead): ?>
              <tr>
                <td><a href="/dashboard/leads/view.php?id=<?php echo (int)$lead['id']; ?>"><?php echo (int)$lead['id']; ?></a></td>
                <td><?php echo htmlspecialchars($lead['type']); ?></td>
                <td><?php echo htmlspecialchars($lead['name']); ?></td>
                <td><?php echo htmlspecialchars($lead['email']); ?></td>
                <td><?php echo htmlspecialchars($lead['service'] ?? ''); ?></td>
                <td><span class="badge-status <?php echo status_badge_class((string)$lead['status']); ?>"><?php echo htmlspecialchars($lead['status']); ?></span></td>
                <td><?php echo htmlspecialchars($lead['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php render_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
  const labels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>;
  const values = <?php echo json_encode($chartValues, JSON_UNESCAPED_UNICODE); ?>;
  const ctx = document.getElementById('leadsChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Leads',
        data: values,
        borderColor: '#0ea5a4',
        backgroundColor: 'rgba(14,165,164,0.15)',
        borderWidth: 2,
        tension: 0.35,
        fill: true,
        pointRadius: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: { display: false },
        y: { beginAtZero: true }
      }
    }
  });
</script>





